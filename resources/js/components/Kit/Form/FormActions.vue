<script setup lang="ts">
import { Link, router, useFormContext } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import Button from './Button.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';

/*
 * The bar at the foot of a form: the form's status, then Cancel, then the submit button, right-aligned. It reads the form from Inertia's <Form> context
 * (or the `form` prop, a useForm() result, which wins). Status, one at a time: busy, errors, unsaved changes (neutral), saved. Sticky by default: pinned to
 * the viewport while the form's end is off-screen (tinted only then, measured with an IntersectionObserver sentinel), released at the form's end.
 * While the form is dirty, leaving the page asks first (an Inertia visit, and closing the tab), and upload progress shows as a bar and a percentage.
 * It is the form's last child; delete never goes here (a separate danger zone).
 */
type FormLike = { processing: boolean; isDirty: boolean; hasErrors: boolean; recentlySuccessful: boolean; progress?: { percentage?: number } | null; reset: (...fields: string[]) => void; clearErrors?: (...fields: string[]) => void; resetAndClearErrors?: () => void };
// The submit may be the server's action descriptor (`:submit="save"`): its label is the button's, and `disabled` with a `reason` shows the reason in the status
// slot and disables the button; a form the viewer cannot use is never a form with nothing to press.
type SubmitAction = { key?: string; label: string; disabled?: boolean; reason?: string | null };
const props = withDefaults(defineProps<{ form?: FormLike; submit?: SubmitAction; submitLabel?: string; busyLabel?: string; savedLabel?: string; cancelUrl?: string; cancel?: boolean; sticky?: boolean; warnOnLeave?: boolean }>(), { form: undefined, submit: undefined, submitLabel: 'Save', busyLabel: 'Saving…', savedLabel: 'Saved', cancelUrl: undefined, cancel: true, sticky: true, warnOnLeave: true });
defineOptions({ inheritAttrs: false });

const context = useFormContext() as FormLike | undefined;
const source = computed<FormLike | undefined>(() => props.form ?? context);
if (!props.form && !context && import.meta.env?.DEV) {
    console.error('<FormActions> needs a form: put it inside Inertia\'s <Form>, or pass a useForm() result as :form.');
}

const processing = computed(() => Boolean(source.value?.processing));
const locked = computed(() => Boolean(props.submit?.disabled));
const label = computed(() => props.submit?.label ?? props.submitLabel);
const progress = computed(() => source.value?.progress?.percentage ?? null);
const state = computed<'busy' | 'errors' | 'dirty' | 'saved' | 'idle' | 'readonly'>(() => {
    if (locked.value && props.submit?.reason) return 'readonly';
    if (processing.value) return 'busy';
    if (source.value?.hasErrors) return 'errors';
    if (source.value?.isDirty) return 'dirty';
    if (source.value?.recentlySuccessful) return 'saved';
    return 'idle';
});
// Unsaved changes, whatever the status shows: errors don't make an edit any less unsaved, so leaving warns on this, not on the status.
// A form showing errors holds input its failed submit didn't save, even where <Form> re-reads its defaults after the error response.
const dirty = computed(() => Boolean(source.value?.isDirty || source.value?.hasErrors));
const statusText = computed(() => {
    if (state.value === 'busy') return progress.value !== null ? `Uploading… ${Math.round(progress.value)}%` : props.busyLabel;
    return { errors: 'There are errors in your submission.', dirty: 'Unsaved changes', saved: props.savedLabel, idle: '', readonly: props.submit?.reason ?? '' }[state.value];
});
const statusClass = computed(() => ({ errors: 'text-destructive', dirty: 'text-muted-foreground', saved: 'text-green-600 dark:text-green-500', busy: 'text-muted-foreground', idle: '', readonly: 'text-muted-foreground' })[state.value]);

function reset() {
    if (source.value?.resetAndClearErrors) source.value.resetAndClearErrors();
    else {
        source.value?.reset();
        source.value?.clearErrors?.();
    }
}

// Sticky: the bar is pinned while the sentinel after it (the form's end) is off-screen.
const bar = ref<HTMLElement>();
const sentinel = ref<HTMLElement>();
const pinned = ref(false);
let observer: IntersectionObserver | undefined;
let frame = 0;
function revealFocus() {
    cancelAnimationFrame(frame);
    // A focused field the pinned bar covers scrolls clear (after the browser's own caret scrolling).
    frame = requestAnimationFrame(() => requestAnimationFrame(() => {
        const active = document.activeElement;
        const form = bar.value?.closest('form');
        if (!props.sticky || !pinned.value || !(active instanceof HTMLElement) || !form?.contains(active) || bar.value?.contains(active)) return;
        const field = active.getBoundingClientRect();
        const footer = bar.value?.getBoundingClientRect();
        if (footer && field.bottom > footer.top && field.top < footer.bottom) active.scrollIntoView({ block: 'center', behavior: 'instant' as ScrollBehavior });
    }));
}
// Leave warning: an Inertia visit away while dirty asks first; the form's own submit goes through; closing the tab asks via beforeunload.
const leaving = ref(false);
const askLeave = ref(false);
let pending: { url: string } | null = null;
let submitting = false;
let offBefore: (() => void) | undefined;
const unload = (event: BeforeUnloadEvent) => {
    if (dirty.value && !leaving.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};
function leave() {
    leaving.value = true;
    if (pending) router.visit(pending.url);
}
onMounted(() => {
    const form = bar.value?.closest('form');
    form?.addEventListener('focusin', revealFocus);
    form?.addEventListener('input', revealFocus);
    form?.addEventListener('submit', () => { submitting = true; setTimeout(() => (submitting = false), 0); }, true);
    if (props.sticky && sentinel.value && 'IntersectionObserver' in window) {
        // Pinned only while the form's end is BELOW the viewport (not when the whole form has been scrolled past).
        observer = new IntersectionObserver(([entry]) => { pinned.value = !entry.isIntersecting && entry.boundingClientRect.top > 0; }, { threshold: 0 });
        observer.observe(sentinel.value);
    }
    if (props.warnOnLeave) {
        offBefore = router.on('before', (event) => {
            const visit = event.detail.visit;
            if (!dirty.value || leaving.value || submitting || visit.method !== 'get' || visit.url.pathname === window.location.pathname) return;
            pending = { url: visit.url.href };
            askLeave.value = true;
            return false;
        });
    }
});
watch(dirty, (now) => {
    if (!props.warnOnLeave) return;
    if (now) window.addEventListener('beforeunload', unload);
    else window.removeEventListener('beforeunload', unload);
}, { immediate: true });
onBeforeUnmount(() => {
    cancelAnimationFrame(frame);
    observer?.disconnect();
    offBefore?.();
    window.removeEventListener('beforeunload', unload);
});
</script>
<template>
    <div ref="bar" data-form-actions :data-state="state" :data-pinned="sticky && pinned" :class="['relative z-5 flex flex-wrap items-center justify-end gap-3 border-t px-6 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] transition-colors', sticky && 'sticky bottom-0', sticky && pinned ? 'border-border bg-background/95 shadow-[0_-4px_12px_-8px_rgb(0_0_0/0.15)]' : 'border-transparent']">
        <div v-if="progress !== null" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.round(progress)" class="absolute inset-x-0 top-0 h-0.5 bg-muted" data-form-progress><div class="h-full bg-primary" :style="{ width: `${progress}%` }" /></div>
        <slot name="status" :state="state">
            <span :class="['min-h-5 flex-1 text-right text-sm', statusClass]" data-form-status :data-state="state">{{ statusText }}</span>
        </slot>
        <span class="sr-only" role="status" aria-live="polite">{{ statusText }}</span>
        <slot name="actions" />
        <template v-if="cancel">
            <Link v-if="cancelUrl" :href="cancelUrl" class="inline-flex h-9 items-center justify-center rounded-md bg-secondary px-4 text-sm font-medium text-secondary-foreground hover:bg-secondary/80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-form-cancel>Cancel</Link>
            <Button v-else variant="secondary" type="button" :disabled="processing" data-form-cancel @click="reset">Cancel</Button>
        </template>
        <slot name="submit">
            <Button type="submit" :disabled="processing || locked" :aria-busy="processing || undefined" data-form-submit :data-action="submit?.key ?? 'save'" v-bind="$attrs">
                <span class="grid">
                    <span :class="['col-start-1 row-start-1', processing && 'invisible']">{{ label }}</span>
                    <span :class="['col-start-1 row-start-1 flex items-center justify-center gap-2', !processing && 'invisible']" aria-hidden="true"><Spinner />{{ busyLabel }}</span>
                </span>
            </Button>
        </slot>
    </div>
    <div ref="sentinel" aria-hidden="true" class="h-px" data-form-sentinel />
    <Dialog v-model:open="askLeave" title="Leave this page?" description="You have unsaved changes. If you leave, they are lost." confirm-label="Leave" cancel-label="Stay" @confirm="leave" />
</template>
