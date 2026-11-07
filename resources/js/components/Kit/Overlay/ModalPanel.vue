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
// Twice: the package's own trap takes focus when the enter animation ends, and that must not leave it on the Close button.
// `initialFocus="body"` (content with no field to start on, such as a popover's working) focuses the body itself, scrolled to the top, instead of the first field.
function focusFirst() {
    if (!body.value) return;
    if (props.initialFocus === 'body') {
        body.value.scrollTop = 0;
        if (document.activeElement !== body.value)
            body.value.focus({ preventScroll: true });
        return;
    }
    if (!body.value.contains(document.activeElement))
        body.value
            .querySelector<HTMLElement>(
                'input:not([type=hidden]), select, textarea, button',
            )
            ?.focus();
}
// The package renders the role=dialog element and gives it no name, so the panel names it by its title (aria-labelledby is a standard attribute, found by role, not by a package class).
onMounted(() => {
    root.value
        ?.closest('[role="dialog"]')
        ?.setAttribute('aria-labelledby', titleId);
    [60, 400].forEach((delay) => setTimeout(focusFirst, delay));
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
    if (unsaved.value) asking.value = true;
    else emit('close');
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
    emit('close');
}
const keep = ref<{ $el: HTMLElement }>();
watch(asking, (now) => {
    if (now) nextTick(() => keep.value?.$el?.focus());
});
defineExpose({ requestClose, titleId });
</script>
<template>
    <div
        ref="root"
        :class="[
            'relative flex flex-col sm:max-h-[calc(100svh-2rem)]',
            fullScreen ? 'max-sm:min-h-svh' : 'max-sm:max-h-[85svh]',
        ]"
        :aria-labelledby="titleId"
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
