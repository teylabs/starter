<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    useAttrs,
    useId,
    type MaybeRefOrGetter,
} from 'vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import Button from './Button.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import {
    useFormFrameStatus,
    useLeaveGuard,
    useSubmitGate,
    type FormLike,
} from './formFrame';

/*
 * The bar at the foot of a form: the form's status, then Cancel, then the submit button, right-aligned. It reads the form from Inertia's <Form> context
 * (or the `form` prop, a useForm() or useSubmitForm() result, which wins). Status, one at a time: busy, errors, unsaved changes (neutral), saved. Sticky by default: pinned to
 * the viewport while the form's end is off-screen (tinted only then, measured with an IntersectionObserver sentinel), released at the form's end.
 * While the page is unsaved (the form dirty, or pending work such as an upload), leaving asks first (an Inertia visit, Back and Forward, and closing the tab;
 * formFrame.ts), and upload progress shows as a bar and a percentage. `warnOnLeave` false registers no guard at all (no router listener, no popstate or
 * navigate listener, no `beforeunload`), so a host that owns its own leave guard has it to itself. Every status line is
 * overridable: `dirtyLabel`, `errorsLabel`, `upToDateLabel` (empty by default) and `savedLabel`.
 * Save is disabled, and Enter does not submit, while it cannot succeed (formFrame.ts's useSubmitGate): pending work in flight (an upload), an open stale-write
 * conflict (a useSubmitForm's `conflict`), or `canSubmit` false (this prop, a boolean, Ref or getter, or a useSubmitForm's option). The button is described by the reason.
 * It is the form's last child; delete never goes here (a separate danger zone).
 */
// The submit may be the server's action descriptor (`:submit="save"`): its label is the button's, and `disabled` with a `reason` shows the reason in the status
// slot and disables the button; a form the viewer cannot use is never a form with nothing to press.
type SubmitAction = {
    key?: string;
    label: string;
    disabled?: boolean;
    reason?: string | null;
};
const props = withDefaults(
    defineProps<{
        form?: FormLike;
        submit?: SubmitAction;
        submitLabel?: string;
        busyLabel?: string;
        savedLabel?: string;
        dirtyLabel?: string;
        errorsLabel?: string;
        upToDateLabel?: string;
        cancelUrl?: string;
        cancel?: boolean;
        sticky?: boolean;
        warnOnLeave?: boolean;
        canSubmit?: MaybeRefOrGetter<boolean>;
    }>(),
    {
        canSubmit: true,
        dirtyLabel: 'Unsaved changes',
        errorsLabel: 'There are errors in your submission.',
        upToDateLabel: '',
        form: undefined,
        submit: undefined,
        submitLabel: 'Save',
        busyLabel: 'Saving…',
        savedLabel: 'Saved',
        cancelUrl: undefined,
        cancel: true,
        sticky: true,
        warnOnLeave: true,
    },
);
defineOptions({ inheritAttrs: false });

const context = useOptionalFormContext() as FormLike | undefined;
const source = computed<FormLike | undefined>(() => props.form ?? context);
if (!props.form && !context && import.meta.env?.DEV) {
    console.error(
        "<FormActions> needs a form: put it inside Inertia's <Form>, or pass a useForm() or useSubmitForm() result as :form.",
    );
}

const locked = computed(() => Boolean(props.submit?.disabled));
const label = computed(() => props.submit?.label ?? props.submitLabel);
// Status, the dirty flag and the leave guard are the form frame's (formFrame.ts), shared with the Workbench's `:form`.
const { processing, progress, state, dirty, statusText, statusClass } =
    useFormFrameStatus(() => source.value, {
        labels: () => props,
        locked: () => locked.value,
        reason: () => props.submit?.reason,
    });

// Why Save cannot succeed now, if it cannot: the button is disabled and described by it, and the form's own submit (Enter) is stopped before it starts.
const gate = useSubmitGate(
    () => source.value,
    () => props.canSubmit,
    `${useId()}-blocked`,
);
const attrs = useAttrs();
// A host's own aria-describedby on the button is kept, after the reason.
const describedBy = computed(
    () =>
        [gate.describedBy.value, attrs['aria-describedby']]
            .filter(Boolean)
            .join(' ') || undefined,
);
function holdSubmit(event: Event) {
    if (!gate.blocked.value) return;
    event.preventDefault();
    event.stopImmediatePropagation();
}

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
    frame = requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            const active = document.activeElement;
            const form = bar.value?.closest('form');
            if (
                !props.sticky ||
                !pinned.value ||
                !(active instanceof HTMLElement) ||
                !form?.contains(active) ||
                bar.value?.contains(active)
            )
                return;
            const field = active.getBoundingClientRect();
            const footer = bar.value?.getBoundingClientRect();
            if (
                footer &&
                field.bottom > footer.top &&
                field.top < footer.bottom
            )
                active.scrollIntoView({
                    block: 'center',
                    behavior: 'instant' as ScrollBehavior,
                });
        }),
    );
}
// Leave warning (formFrame.ts): off with `warn-on-leave` false here, or `warnOnLeave: false` on a useSubmitForm.
const { askLeave, leave } = useLeaveGuard(
    () => dirty.value,
    () => props.warnOnLeave && source.value?.warnOnLeave !== false,
    () => bar.value?.closest('form'),
    () => processing.value,
);
let ownForm: HTMLFormElement | null | undefined;
onMounted(() => {
    const form = bar.value?.closest('form');
    ownForm = form;
    // Capture on the form itself: it runs before the form's own submit handlers (Inertia's <Form>, FormCard), so a blocked save never starts.
    form?.addEventListener('submit', holdSubmit, true);
    form?.addEventListener('focusin', revealFocus);
    form?.addEventListener('input', revealFocus);
    if (props.sticky && sentinel.value && 'IntersectionObserver' in window) {
        // Pinned only while the form's end is BELOW the viewport (not when the whole form has been scrolled past).
        observer = new IntersectionObserver(
            ([entry]) => {
                pinned.value =
                    !entry.isIntersecting && entry.boundingClientRect.top > 0;
            },
            { threshold: 0 },
        );
        observer.observe(sentinel.value);
    }
});
onBeforeUnmount(() => {
    ownForm?.removeEventListener('submit', holdSubmit, true);
    cancelAnimationFrame(frame);
    observer?.disconnect();
});
</script>
<template>
    <div
        ref="bar"
        data-form-actions
        :data-state="state"
        :data-pinned="sticky && pinned"
        :class="[
            'relative z-5 flex flex-wrap items-center justify-end gap-3 border-t px-6 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] transition-colors',
            sticky && 'sticky bottom-0',
            sticky && pinned
                ? 'border-border bg-background/95 shadow-[0_-4px_12px_-8px_rgb(0_0_0/0.15)]'
                : 'border-transparent',
        ]"
    >
        <div
            v-if="progress !== null"
            role="progressbar"
            aria-label="Upload progress"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="Math.round(progress)"
            class="bg-muted absolute inset-x-0 top-0 h-0.5"
            data-form-progress
        >
            <div
                class="bg-primary h-full w-(--kit-form-progress)"
                :style="{ '--kit-form-progress': `${progress}%` }"
            />
        </div>
        <slot name="status" :state="state">
            <span
                :class="['min-h-5 flex-1 text-right text-sm', statusClass]"
                data-form-status
                :data-state="state"
                >{{ statusText }}</span
            >
        </slot>
        <span class="sr-only" role="status" aria-live="polite">{{
            statusText
        }}</span>
        <slot name="actions" />
        <template v-if="cancel">
            <Link
                v-if="cancelUrl"
                :href="cancelUrl"
                class="bg-secondary text-secondary-foreground hover:bg-secondary/80 focus-visible:ring-ring inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none"
                data-form-cancel
                >Cancel</Link
            >
            <Button
                v-else
                variant="secondary"
                type="button"
                :disabled="processing"
                data-form-cancel
                @click="reset"
                >Cancel</Button
            >
        </template>
        <slot name="submit">
            <Button
                type="submit"
                :disabled="processing || locked || !!gate.blocked.value"
                :aria-busy="processing || undefined"
                data-form-submit
                :data-action="submit?.key ?? 'save'"
                :data-blocked="gate.blocked.value ?? undefined"
                :title="gate.reason.value ?? undefined"
                v-bind="$attrs"
                :aria-describedby="describedBy"
            >
                <span class="grid">
                    <span
                        :class="[
                            'col-start-1 row-start-1',
                            processing && 'invisible',
                        ]"
                        >{{ label }}</span
                    >
                    <span
                        :class="[
                            'col-start-1 row-start-1 flex items-center justify-center gap-2',
                            !processing && 'invisible',
                        ]"
                        aria-hidden="true"
                        ><Spinner />{{ busyLabel }}</span
                    >
                </span>
            </Button>
        </slot>
        <span
            v-if="gate.reason.value"
            :id="gate.describedBy.value"
            class="sr-only"
            data-form-blocked
            >{{ gate.reason.value }}</span
        >
    </div>
    <div ref="sentinel" aria-hidden="true" class="h-px" data-form-sentinel />
    <Dialog
        v-model:open="askLeave"
        title="Leave this page?"
        description="You have unsaved changes. If you leave, they are lost."
        confirm-label="Leave"
        cancel-label="Stay"
        @confirm="leave"
    />
</template>
