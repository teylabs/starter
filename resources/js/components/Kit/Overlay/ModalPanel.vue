<script setup lang="ts">
import { X } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
} from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import FormActions from '@/components/Kit/Form/FormActions.vue';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import { provideLayer } from '@/components/Kit/Overlay/layer';

// INTERNAL to <Modal>; pages never import it. The panel's insides: a fixed header (title, description, an `actions` slot, the Close button), a body that scrolls, and the footer (FormActions
// when the modal is a form, the `footer` slot otherwise), capped at the viewport. It asks before closing with unsaved edits, through <Dialog variant="danger"> over the modal.
const props = defineProps<{
    title: string;
    description?: string;
    formMode: boolean;
    submit?: { label: string; url: string; method: string };
    busyLabel: string;
    cancelLabel: string;
    showCloseButton: boolean;
    initialFocus: 'field' | 'body';
    confirmMessage: string;
    dirty: boolean;
    fullScreen: boolean;
    checks: Set<() => boolean>;
}>();
const emit = defineEmits<{ close: [] }>();
const titleId = useId();
const asking = ref(false);
const body = ref<HTMLElement>();
const root = ref<HTMLElement>();
// Focus enters the first field (the package's trap would start at the Close button); the trap and the return to the opener stay the package's.
// `initialFocus="body"` (content with no field to start on, such as a popover's working) focuses the body itself, scrolled to the top, instead of the first field.
// With neither, focus stays where the package puts it.
function startTarget(): HTMLElement | null {
    if (!body.value) return null;
    if (props.initialFocus === 'body') return body.value;
    return body.value.querySelector<HTMLElement>(
        'input:not([type=hidden]), select, textarea, button',
    );
}
function focusStart(target: HTMLElement) {
    if (target === body.value) body.value.scrollTop = 0;
    if (document.activeElement !== target)
        target.focus({ preventScroll: true });
}
// The package's trap takes focus to its first focusable (the Close button) one frame after its enter animation ends, however long that takes. So the panel
// focuses its start now and, while the modal opens, moves focus back to the start once if it lands on the trap's pick (the container's first focusable).
// Nothing else is ever pulled back, and the window is short-lived:
// - any key or pointer press anywhere ends it at once, removing the document listeners; if the viewer moved focus before the trap fired, only a passive watch
//   on the entered marker stays, to put their focus back where it was when the trap takes it;
// - the package marking the panel entered (data-inertiaui-modal-entered="true") ends it once the trap's frame has passed;
// - with no marker to watch it ends after a second, with one it ends after five whatever happens; closing or unmounting the panel ends it too.
// Every step is guarded: once the panel is closing (Close, Cancel, Escape, Discard) or has left the page, nothing here moves focus or starts listening again.
let stopOpening = () => {};
let closing = false;
const live = () =>
    !closing &&
    !!root.value?.isConnected &&
    !!root.value.closest('dialog, [role="dialog"]');
function firstTabbable(container: Element): HTMLElement | null {
    return (
        [
            ...container.querySelectorAll<HTMLElement>(
                'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])',
            ),
        ].find(
            (el) =>
                el.tabIndex >= 0 &&
                !el.matches(':disabled, [type=hidden]') &&
                el.getClientRects().length > 0,
        ) ?? null
    );
}
// With nothing tabbable in the modal (no Close button, no field yet), the panel itself takes focus, so focus is inside the modal rather than left on the page.
const panelFocus = ref(false);
function focusPanel() {
    panelFocus.value = true;
    nextTick(() => {
        if (live() && !root.value?.contains(document.activeElement))
            root.value?.focus({ preventScroll: true });
    });
}
// Content that mounts after the panel (an async component, a slot filled once data arrives): no start yet, so the panel waits for one while the modal opens,
// and starts the opening window when it appears. Any key or pointer press, closing, or five seconds ends the wait.
function awaitStart(container: Element) {
    if (!firstTabbable(container)) focusPanel();
    if (!body.value) return;
    const observer = new MutationObserver(() => {
        if (!live()) return stopOpening();
        if (!startTarget()) return;
        stopOpening();
        watchOpening();
    });
    const cap = setTimeout(() => stopOpening(), 5000);
    const onInput = () => stopOpening();
    stopOpening = () => {
        stopOpening = () => {};
        observer.disconnect();
        clearTimeout(cap);
        document.removeEventListener('keydown', onInput, true);
        document.removeEventListener('pointerdown', onInput, true);
    };
    observer.observe(body.value, { childList: true, subtree: true });
    document.addEventListener('keydown', onInput, true);
    document.addEventListener('pointerdown', onInput, true);
}
function watchOpening() {
    if (!live()) return;
    const container = root.value!.closest('dialog, [role="dialog"]')!;
    const found = startTarget();
    if (!found) return awaitStart(container);
    const start: HTMLElement = found;
    const trapPick = () => firstTabbable(container);
    const entered = root.value?.closest('[data-inertiaui-modal-entered]');
    const isEntered = () =>
        entered?.getAttribute('data-inertiaui-modal-entered') === 'true';
    // After the viewer's input: where their focus was before the trap moved it, restored once.
    let kept: Element | null = null;
    const afterTrap = (then: () => void) =>
        requestAnimationFrame(() =>
            requestAnimationFrame(() => requestAnimationFrame(then)),
        );
    const onMarker = () => {
        if (!isEntered()) return;
        observer.disconnect();
        // `kept` is read when the trap's frame has passed, so a press during these frames counts.
        afterTrap(() => {
            if (!live()) return stopOpening();
            const now = document.activeElement;
            if (
                kept instanceof HTMLElement &&
                kept.isConnected &&
                now !== kept &&
                now === trapPick()
            )
                kept.focus({ preventScroll: true });
            stopOpening();
        });
    };
    const observer = new MutationObserver(onMarker);
    const removeListeners = () => {
        document.removeEventListener('keydown', onInput, true);
        document.removeEventListener('pointerdown', onInput, true);
        document.removeEventListener('focusin', onFocusIn, true);
    };
    function onInput() {
        removeListeners();
        // Read after the press has moved focus; with no marker pending there is nothing left to watch.
        setTimeout(() => {
            if (!live()) return stopOpening();
            kept = document.activeElement;
            if (!entered || isEntered()) stopOpening();
        });
    }
    function onFocusIn(event: FocusEvent) {
        if (!live()) return stopOpening();
        if (event.target === start || event.target !== trapPick()) return;
        stopOpening();
        focusStart(start);
    }
    const cap = setTimeout(() => stopOpening(), entered ? 5000 : 1000);
    stopOpening = () => {
        stopOpening = () => {};
        observer.disconnect();
        clearTimeout(cap);
        removeListeners();
    };
    if (entered)
        observer.observe(entered, {
            attributes: true,
            attributeFilter: ['data-inertiaui-modal-entered'],
        });
    document.addEventListener('keydown', onInput, true);
    document.addEventListener('pointerdown', onInput, true);
    document.addEventListener('focusin', onFocusIn, true);
    focusStart(start);
    // A zero-length or reduced-motion enter may have set the marker before the panel looks.
    onMarker();
}
onBeforeUnmount(() => {
    closing = true;
    stopOpening();
});
// Modal calls `settle` before any close it starts itself (a successful submit, `open` set to false, its exposed `close`), so the opening window ends as the leave begins.
function settle() {
    closing = true;
    stopOpening();
}
function close() {
    settle();
    emit('close');
}
// The package renders the dialog element (a native <dialog> by default, or a role=dialog one) and gives it no name, so the panel names it by its title
// (aria-labelledby is a standard attribute, found by element or role, not by a package class).
onMounted(() => {
    root.value
        ?.closest('dialog, [role="dialog"]')
        ?.setAttribute('aria-labelledby', titleId);
    nextTick(watchOpening);
});
// The package's panel is a native <dialog> in the top layer: the overlays inside it (this confirmation, menus, selects, popovers) render into that <dialog>
// rather than <body>, or they would paint under it and take no clicks (layer.ts). Escape in one of those overlays must close only the overlay: the trusted key
// would also make the browser close the whole <dialog> (its `cancel`), so its default is prevented and the overlay gets an untrusted copy, which carries no
// close request. (Preventing `cancel` instead is not reliable: browsers stop asking after repeated cancels and close the dialog outright.) Escape in the panel
// itself is requestClose, below.
const dialog = ref<HTMLElement | null>(null);
provideLayer(dialog);
function onEscape(event: KeyboardEvent) {
    const target = event.target;
    if (
        event.key !== 'Escape' ||
        !event.isTrusted ||
        !dialog.value ||
        !(target instanceof Node)
    )
        return;
    if (
        !dialog.value.contains(target) ||
        target === dialog.value ||
        root.value?.contains(target)
    )
        return;
    event.preventDefault();
    event.stopImmediatePropagation();
    target.dispatchEvent(
        new KeyboardEvent('keydown', {
            key: 'Escape',
            code: 'Escape',
            bubbles: true,
            cancelable: true,
        }),
    );
}
onMounted(() => {
    dialog.value = root.value?.closest('dialog') ?? null;
    window.addEventListener('keydown', onEscape, true);
});
onBeforeUnmount(() => window.removeEventListener('keydown', onEscape, true));
const unsaved = computed(
    () => props.dirty || [...props.checks].some((check) => check()),
);
function requestClose() {
    stopOpening();
    if (unsaved.value) asking.value = true;
    else close();
}
// Discard closes the confirmation first; the modal closes only once the confirmation has handed focus back inside it, so the modal's own trap returns focus to its opener.
let discarding = false;
function discard() {
    discarding = true;
    asking.value = false;
}
function confirmationClosed() {
    if (!discarding) return;
    discarding = false;
    close();
}
const keep = ref<{ $el: HTMLElement }>();
watch(asking, (now) => {
    if (now) nextTick(() => keep.value?.$el?.focus());
});
defineExpose({ requestClose, settle, titleId });
</script>
<template>
    <div
        ref="root"
        :class="[
            'relative flex flex-col outline-none sm:max-h-[calc(100svh-2rem)]',
            fullScreen ? 'max-sm:min-h-svh' : 'max-sm:max-h-[85svh]',
        ]"
        :aria-labelledby="titleId"
        :tabindex="panelFocus ? -1 : undefined"
        @keydown.esc.stop.prevent="requestClose"
    >
        <header
            class="flex shrink-0 items-start justify-between gap-4 px-6 pt-6 pb-4"
        >
            <div class="grid gap-1">
                <h2 :id="titleId" class="text-lg font-semibold">{{ title }}</h2>
                <p v-if="description" class="text-muted-foreground text-sm">
                    {{ description }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
                <Button
                    v-if="showCloseButton"
                    variant="secondary"
                    type="button"
                    aria-label="Close"
                    class="size-9 px-0 max-sm:size-11"
                    data-modal-close
                    @click="requestClose"
                    ><X class="size-4" aria-hidden="true"
                /></Button>
            </div>
        </header>
        <div
            ref="body"
            :tabindex="initialFocus === 'body' ? -1 : undefined"
            class="grid min-h-0 gap-4 overflow-y-auto px-6 pb-4 outline-none"
            data-modal-body
        >
            <slot />
        </div>
        <FormActions
            v-if="formMode && submit"
            :submit="submit"
            :busy-label="busyLabel"
            :cancel="false"
            :warn-on-leave="false"
            class="border-border shrink-0 border-t"
        >
            <template #actions
                ><Button
                    variant="secondary"
                    type="button"
                    data-modal-cancel
                    @click="requestClose"
                    >{{ cancelLabel }}</Button
                ></template
            >
        </FormActions>
        <div
            v-else-if="$slots.footer"
            class="border-border flex shrink-0 flex-wrap items-center justify-end gap-2 border-t px-6 py-3"
        >
            <slot name="footer" :close="requestClose" />
        </div>
        <Dialog
            variant="danger"
            v-model:open="asking"
            title="Discard your changes?"
            :description="confirmMessage"
            confirm-label="Discard &amp; close"
            cancel-label="Keep editing"
            @confirm="discard"
            @closed="confirmationClosed"
        />
    </div>
</template>
