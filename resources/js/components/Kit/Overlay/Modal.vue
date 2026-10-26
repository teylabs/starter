<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Modal as PackageModal, visitModal } from '@inertiaui/modal-vue';
import { computed, inject, onBeforeUnmount, onMounted, provide, ref, useId, watch } from 'vue';
import ModalPanel from '@/components/Kit/Overlay/ModalPanel.vue';

/*
 * The kit's Modal: content and forms in a layer over the page. ONE component, two ways to use it, with the same props, slots and behaviour:
 *  - as the root of a server-driven modal page (inertiaui/modal: the route owns the URL, the authorisation and the props, the opener's URL is the server's, a direct visit renders the page
 *    behind it). It detects that context by itself; close, leave, the stack and history are the package's.
 *  - locally, anywhere, declared in the template and opened with `v-model:open`.
 * The panel is a column capped at the viewport: a fixed header (title, description, an `actions` slot, Close), a body that scrolls (the default slot) and a footer. With `submit` (the server's
 * action) it is a form: Inertia's <Form> wraps the panel and FormActions is the sticky footer, so the footer submits it and sees its state; a successful submit closes it and the page behind
 * refreshes; validation errors stay in. Without it the footer is the `footer` slot (its slot prop `close` asks before closing).
 *
 * Closing is explicit (Escape, Close and Cancel; the backdrop never closes). With unsaved edits (the form's, the `dirty` prop's, or a control registered with the injected `kit.modal-guard`) it
 * asks first through <Dialog> ("Keep editing" / "Discard & close"; `confirm-message` words it). `size` is sm, md, lg, xl or full; `placement` is center (the default), slideover (from the side) or drawer (a bottom sheet at every width); `as="form"` (with `submit`) makes the panel a form. On a phone sm and md are a
 * bottom sheet, and lg, xl, full and slideovers are full screen. Confirmations and prompts are <Dialog>, not this.
 */
const props = withDefaults(defineProps<{ title: string; description?: string; size?: 'sm' | 'md' | 'lg' | 'xl' | 'full'; placement?: 'center' | 'slideover' | 'drawer'; as?: 'form' | 'div'; submit?: { label: string; url: string; method: string }; dirty?: boolean; busyLabel?: string; cancelLabel?: string; showCloseButton?: boolean; confirmMessage?: string }>(), {
    size: 'lg',
    placement: 'center',
    dirty: false,
    busyLabel: 'Saving…',
    cancelLabel: 'Cancel',
    showCloseButton: true,
    confirmMessage: 'You have unsaved changes. Are you sure you want to close without saving?',
});
const open = defineModel<boolean>('open');
const emit = defineEmits<{ closed: [] }>();

// Server-driven when rendered by the package's modal renderer (it provides `modalContext`); local otherwise.
const injected = inject<{ value: unknown } | null>('modalContext', null);
const server = Boolean(injected?.value);
const localName = `kit-modal-${useId()}`;
// The package ships no declaration for its Modal component, so its instance is typed here by the one method used.
const modal = ref<{ close: () => void } | null>(null);
const checks = new Set<() => boolean>();
provide('kit.modal-guard', { register: (check: () => boolean) => checks.add(check), unregister: (check: () => boolean) => checks.delete(check) });

const widths: Record<string, string> = { sm: 'md', md: 'lg', lg: '2xl', xl: '4xl', full: '7xl' };
const maxWidth = computed(() => widths[props.size]);
const slideover = computed(() => props.placement === 'slideover');
const formMode = computed(() => (props.as ?? (props.submit ? 'form' : 'div')) === 'form' && Boolean(props.submit));
const fullScreen = computed(() => slideover.value || props.size !== 'sm' && props.size !== 'md');
// The package aligns the panel by `position` and pads its container: a phone's bottom sheet is `position="bottom"`, and both bleed out of that padding with negative margins.
const phone = ref(false);
let query: MediaQueryList | undefined;
const track = () => (phone.value = Boolean(query?.matches));
onMounted(() => {
    query = window.matchMedia('(max-width: 639px)');
    track();
    query.addEventListener('change', track);
});
const position = computed(() => (slideover.value ? undefined : props.placement === 'drawer' || (phone.value && !fullScreen.value) ? 'bottom' : 'center'));
const panelClasses = computed(() => ['overflow-hidden border border-border bg-background text-foreground shadow-lg sm:rounded-xl', fullScreen.value ? 'max-sm:-m-4 max-sm:w-[calc(100%+2rem)] max-sm:rounded-none max-sm:border-0' : 'max-sm:-mx-4 max-sm:-mb-4 max-sm:w-[calc(100%+2rem)] max-sm:rounded-t-xl max-sm:border-b-0'].join(' '));

// Focus returns to the opener: the package does not, so once the panel has left and focus has fallen to the page, the element focused when it opened gets it back.
let opener: HTMLElement | null = null;
const remember = () => (opener = document.activeElement instanceof HTMLElement && document.activeElement !== document.body ? document.activeElement : null);
function returnFocus() {
    const target = opener;
    opener = null;
    const started = Date.now();
    const settle = () => {
        if (!target?.isConnected) return;
        const active = document.activeElement;
        if (!active || active === document.body) return target.focus({ preventScroll: true });
        // Still inside the leaving panel: wait for it to go; anywhere else, the viewer moved focus on purpose.
        if (active.closest('.im-modal-content, [role="dialog"]') && Date.now() - started < 1500) setTimeout(settle, 50);
    };
    setTimeout(settle, 50);
}
if (server) onMounted(remember);
onBeforeUnmount(() => { if (server) returnFocus(); });

// Local use: `open` shows and hides the package's named modal; its own close (Escape, Close, Cancel) sets it back.
let handle: { close: () => void } | null = null;
const opened = (instance: unknown) => (handle = instance as { close: () => void });
watch(() => open.value, async (now) => {
    if (server) return;
    if (now && !handle) {
        remember();
        opened(await visitModal(`#${localName}`, { onClose: () => { handle = null; open.value = false; returnFocus(); emit('closed'); } }));
    } else if (!now && handle) {
        handle.close();
    }
}, { immediate: true, flush: 'post' });
onBeforeUnmount(() => query?.removeEventListener('change', track));
const close = () => modal.value?.close();
defineExpose({ close });
</script>
<template>
    <PackageModal ref="modal" v-bind="server ? {} : { name: localName }" :close-button="false" close-explicitly :slideover="slideover" :max-width="maxWidth" padding-classes="" :panel-classes="panelClasses" :position="position">
        <Form v-if="formMode" v-slot="{ isDirty }" :action="submit!.url" :method="submit!.method as 'post'" :options="{ preserveScroll: true }" class="contents" data-form-modal @success="close()">
            <ModalPanel :title="title" :description="description" :form-mode="true" :submit="submit" :busy-label="busyLabel" :cancel-label="cancelLabel" :show-close-button="showCloseButton" :confirm-message="confirmMessage" :dirty="isDirty || dirty" :full-screen="fullScreen" :checks="checks" @close="close()">
                <template #actions><slot name="actions" /></template>
                <slot />
            </ModalPanel>
        </Form>
        <ModalPanel v-else :title="title" :description="description" :form-mode="false" :busy-label="busyLabel" :cancel-label="cancelLabel" :show-close-button="showCloseButton" :confirm-message="confirmMessage" :dirty="dirty" :full-screen="fullScreen" :checks="checks" data-modal @close="close()">
            <template #actions><slot name="actions" /></template>
            <slot />
            <template v-if="$slots.footer" #footer="{ close: ask }"><slot name="footer" :close="ask" /></template>
        </ModalPanel>
    </PackageModal>
</template>
