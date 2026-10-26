<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, inject, nextTick, onBeforeUnmount, onMounted, provide, ref, useId, watch } from 'vue';
import { PopoverAnchor, PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import Modal from '@/components/Kit/Overlay/Modal.vue';
import { popoverMessages, popoverWidths, sheetKey } from './popover';
import type { PopoverDismiss, PopoverMessages, PopoverWidth } from './popover';

// The kit's one non-modal surface (Popover.md): contextual detail anchored to a trigger, on Reka's Popover. Never modal on desktop; below 640px (`sheet`) the same state and slots
// render in the kit Modal as a bottom sheet. Nesting is Reka's dismissable layers; a Popover opened inside a sheet stays a popover. Collision handling is Reka's alone: a host that must keep
// the panel clear of a fixed bar passes it as `collisionBoundary` or raises `collisionPadding`. Strings come from the kit's `ui` lang file (`kitUi`), then `messages`.
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{
    title?: string; label?: string; side?: 'top' | 'right' | 'bottom' | 'left'; align?: 'start' | 'center' | 'end'; offset?: number; width?: PopoverWidth;
    closeButton?: boolean; collisionPadding?: number; collisionBoundary?: Element | Element[] | null; sheet?: boolean; messages?: Partial<PopoverMessages>;
}>(), { title: undefined, label: undefined, side: 'bottom', align: 'start', offset: 8, width: 'auto', closeButton: true, collisionPadding: 16, collisionBoundary: null, sheet: true, messages: () => ({}) });
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ dismiss: [reason: PopoverDismiss] }>();
const slots = defineSlots<{ trigger?(props: { open: boolean; close: () => void }): unknown; anchor?(): unknown; title?(): unknown; default?(props: { close: () => void }): unknown; footer?(props: { close: () => void }): unknown }>();

const uid = useId();
const headingId = `${uid}-title`;
const fallbackTriggerId = `${uid}-trigger`;
const insideSheet = inject(sheetKey, false);
const text = computed(() => {
    let shared: unknown;
    try { shared = (usePage().props as { kitUi?: { popover?: unknown } }).kitUi?.popover; } catch { shared = undefined; }
    return popoverMessages(shared, props.messages);
});
const hasTitle = computed(() => Boolean(props.title) || Boolean(slots.title));
const sheetName = computed(() => props.title ?? props.label ?? text.value.close);

const phone = ref(false);
let query: MediaQueryList | undefined;
const track = () => { phone.value = query?.matches ?? false; };
onMounted(() => { query = matchMedia('(max-width: 639px)'); track(); query.addEventListener('change', track); });
onBeforeUnmount(() => query?.removeEventListener('change', track));
const asSheet = computed(() => props.sheet && phone.value && !insideSheet);
provide(sheetKey, insideSheet || asSheet.value);

const trigger = ref<{ $el: HTMLElement } | null>(null);
const anchor = ref<{ $el: HTMLElement } | null>(null);
const triggerEl = () => trigger.value?.$el ?? null;
// The desktop panel, found by its `data-kit-popover` hook (a host hook too): the content ref's `$el` can be Reka's presence wrapper rather than the element.
const panelEl = () => (document.querySelector<HTMLElement>(`[data-kit-popover="${uid}"]`) ?? null);
let opener: HTMLElement | null = null;
let reason: PopoverDismiss | null = null;
let focusFollows = false;

// Closes for a user-caused reason: the state changes and the reason is reported once. A parent setting `open` to false reports nothing.
function dismiss(why: PopoverDismiss) {
    if (!open.value) return;
    reason = why;
    open.value = false;
    emit('dismiss', why);
}
const close = () => dismiss('close');
const desktopOpen = computed({ get: () => open.value && !asSheet.value, set: (value: boolean) => { if (!value) dismiss('outside'); else open.value = true; } });
const sheetOpen = computed({ get: () => open.value && asSheet.value, set: (value: boolean) => { if (!value) dismiss('close'); else open.value = true; } });

// The panel is named by its heading, else `label`, else the trigger. Reka merges `aria-labelledby = triggerId` after forwarded props, so the name is set on the element
// once it exists; Reka never patches it again while its trigger id is unchanged.
const labelledBy = computed(() => (hasTitle.value ? headingId : props.label ? undefined : triggerEl()?.id || undefined));
watch(open, (now) => {
    if (now) {
        reason = null;
        opener = triggerEl() ?? (document.activeElement instanceof HTMLElement ? document.activeElement : null);
        const el = triggerEl();
        if (el && !el.id) el.id = fallbackTriggerId;
    } else if (asSheet.value) {
        nextTick(() => { if (reason !== 'navigate') (triggerEl() ?? opener)?.focus({ preventScroll: true }); });
    }
});

function nameUp(el: HTMLElement) {
    if (labelledBy.value) el.setAttribute('aria-labelledby', labelledBy.value); else el.removeAttribute('aria-labelledby');
    if (!hasTitle.value && props.label) el.setAttribute('aria-label', props.label); else el.removeAttribute('aria-label');
}
// Reka's open auto-focus is replaced: on the next tick the panel itself (tabindex -1) takes focus, retried a frame later if Reka's focus scope moved it back.
function focusPanel(event: Event) {
    event.preventDefault();
    const take = () => { const el = panelEl(); if (!el) return; nameUp(el); if (!el.contains(document.activeElement)) el.focus({ preventScroll: true }); };
    nextTick(() => { take(); requestAnimationFrame(take); });
}
watch([labelledBy, () => props.label], () => { const el = panelEl(); if (el) nameUp(el); });
// Focus on close: Escape, the close button and `close()` return it to the trigger; an outside press leaves it where the press put it unless that was nothing focusable;
// Tab leaving the panel and a route change leave it alone.
function returnFocus(event: Event) {
    event.preventDefault();
    if (reason === 'navigate' || focusFollows) return;
    const active = document.activeElement;
    if (reason === 'outside' && active && active !== document.body && !panelEl()?.contains(active)) return;
    (triggerEl() ?? opener)?.focus({ preventScroll: true });
}
// Tab past the last control leaves in document order after the trigger and closes; Shift+Tab before the first returns to the trigger and stays open.
function leave(event: KeyboardEvent) {
    if (event.key !== 'Tab') return;
    const panel = panelEl();
    const controls = [...(panel?.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])') ?? [])];
    const target = event.target as HTMLElement;
    if (event.shiftKey && (target === panel || target === controls[0])) { event.preventDefault(); triggerEl()?.focus({ preventScroll: true }); return; }
    if (!event.shiftKey && (controls.length === 0 ? target === panel : target === controls[controls.length - 1])) {
        event.preventDefault();
        const after = triggerEl();
        const tabbable = [...document.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')].filter(el => !panel?.contains(el));
        focusFollows = true;
        dismiss('outside');
        const next = after ? tabbable.find(el => after.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING) : tabbable[0];
        (next ?? after)?.focus({ preventScroll: true });
        nextTick(() => { focusFollows = false; });
    }
}
// A navigation closes every open Popover without taking focus; the anchor leaving the boundary closes it.
const stopNavigation = router.on('start', () => { if (open.value) dismiss('navigate'); });
function detached() {
    const el = (anchor.value?.$el ?? triggerEl());
    if (!open.value || asSheet.value || !el) return;
    const box = el.getBoundingClientRect();
    if (box.bottom < 0 || box.top > innerHeight || box.right < 0 || box.left > innerWidth) dismiss('outside');
}
onMounted(() => addEventListener('scroll', detached, true));
onBeforeUnmount(() => { stopNavigation(); removeEventListener('scroll', detached, true); });
</script>
<template>
    <PopoverRoot v-model:open="desktopOpen" :modal="false">
        <PopoverAnchor v-if="slots.anchor" ref="anchor" as-child><slot name="anchor" /></PopoverAnchor>
        <PopoverTrigger v-if="slots.trigger" ref="trigger" as-child><slot name="trigger" :open="open" :close="close" /></PopoverTrigger>
        <PopoverPortal v-if="!asSheet">
            <PopoverContent
                v-bind="$attrs" :data-kit-popover="uid" tabindex="-1" :side="side" :align="align" :side-offset="offset" :collision-padding="collisionPadding" :collision-boundary="collisionBoundary ?? undefined" hide-when-detached sticky="always"
                :aria-labelledby="labelledBy" :aria-label="hasTitle ? undefined : label"
                :class="['z-50 max-h-[min(90dvh,var(--reka-popover-content-available-height))] min-w-0 overflow-x-hidden overflow-y-auto wrap-anywhere rounded-lg border border-border bg-popover p-4 text-popover-foreground shadow-lg focus-visible:ring-2 focus-visible:ring-ring', popoverWidths[width], $attrs.class]"
                @escape-key-down="dismiss('escape')" @open-auto-focus="focusPanel" @close-auto-focus="returnFocus" @keydown="leave" @click="(event: MouseEvent) => { if ((event.target as HTMLElement).closest('a[href]')) dismiss('navigate'); }">
                <div v-if="hasTitle || closeButton" class="mb-2 flex items-start gap-2">
                    <h2 v-if="hasTitle" :id="headingId" class="min-w-0 flex-1 text-sm font-semibold text-foreground"><slot name="title">{{ title }}</slot></h2><span v-else class="flex-1" />
                    <button v-if="closeButton" type="button" :aria-label="text.close" class="-mt-1 -mr-2 inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring" @click="close"><X class="size-4" aria-hidden="true" /></button>
                </div>
                <slot :close="close" />
                <div v-if="slots.footer" class="mt-3 border-t border-border pt-3"><slot name="footer" :close="close" /></div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
    <!-- Below 640px the same state and slots render in the kit Modal as a bottom sheet; Modal owns the trap, the scroll lock, Escape and focus. -->
    <Modal v-if="asSheet" v-model:open="sheetOpen" :title="sheetName" size="sm" placement="drawer" initial-focus="body" :show-close-button="closeButton">
        <div class="min-w-0 wrap-anywhere"><slot :close="close" /><div v-if="slots.footer" class="mt-3 border-t border-border pt-3"><slot name="footer" :close="close" /></div></div>
    </Modal>
</template>
