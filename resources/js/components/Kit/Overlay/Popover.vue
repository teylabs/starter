<script setup lang="ts">
import { computed, inject, nextTick, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { X } from '@lucide/vue';
import Modal from '@/components/Kit/Overlay/Modal.vue';
import { overlayKey } from '@/components/Kit/Explanation/scope';

// Internal to ComputedValue: not exported from @kit/ui while its public shape is settled. Desktop anchors with Reka; a phone hands off to the kit Modal's bottom sheet.
const props = defineProps<{ title: string }>();
const open = defineModel<boolean>('open', { default: false });
const nested = inject(overlayKey, false);
provide(overlayKey, true);
const phone = ref(false);
const headerBottom = ref(16);
function measureHeader() { headerBottom.value = Math.max(16, (document.querySelector('[data-shell-top]')?.getBoundingClientRect().bottom ?? 0) + 8); }
const opener = ref<HTMLElement>();
const sheet = ref<HTMLElement>();
let query: MediaQueryList | undefined;
const track = () => { phone.value = query?.matches ?? false; };
onMounted(() => { measureHeader(); window.addEventListener('resize', measureHeader); window.addEventListener('scroll', measureHeader, true); query = matchMedia('(max-width: 639px)'); track(); query.addEventListener('change', track); });
onBeforeUnmount(() => { window.removeEventListener('resize', measureHeader); window.removeEventListener('scroll', measureHeader, true); query?.removeEventListener('change', track); });
const desktopOpen = computed({ get: () => open.value && !phone.value && !nested, set: v => { open.value = v; } });
const sheetOpen = computed({ get: () => open.value && phone.value && !nested, set: v => { open.value = v; } });
function remember(event: Event) { measureHeader(); opener.value = (event.target as HTMLElement).closest('button') ?? event.currentTarget as HTMLElement; }
function focusPanel(event: Event) {
    event.preventDefault();
    nextTick(() => document.querySelector<HTMLElement>('[data-kit-popover]')?.focus());
}
// The phone sheet: once the kit Modal renders it, its dialog is labelled by the modal's title ("Working for <label>"), and focus starts at the top of the working only after
// the modal has entered (the package's own trap moves focus when its enter transition ends, which would otherwise win).
let entered: MutationObserver | undefined;
function focusSheet() {
    sheet.value?.focus({ preventScroll: true });
    const body = sheet.value?.closest('[data-modal-body]');
    if (body) body.scrollTop = 0;
}
watch(sheet, (panel) => {
    entered?.disconnect();
    if (!panel) return;
    const titleId = panel.closest('[data-modal]')?.getAttribute('aria-labelledby');
    if (titleId) panel.closest('[role="dialog"]')?.setAttribute('aria-labelledby', titleId);
    const content = panel.closest('[data-inertiaui-modal-entered]');
    if (!content || content.getAttribute('data-inertiaui-modal-entered') === 'true') return void requestAnimationFrame(focusSheet);
    entered = new MutationObserver(() => {
        if (content.getAttribute('data-inertiaui-modal-entered') !== 'true') return;
        entered?.disconnect();
        requestAnimationFrame(focusSheet);
    });
    entered.observe(content, { attributes: true, attributeFilter: ['data-inertiaui-modal-entered'] });
}, { flush: 'post' });
onBeforeUnmount(() => entered?.disconnect());
function returnFocus() { entered?.disconnect(); nextTick(() => opener.value?.focus()); }
function close() { open.value = false; if (!phone.value) returnFocus(); }
</script>
<template>
    <template v-if="nested"><slot name="trigger" :open="false" :toggle="() => {}" /><slot /></template>
    <template v-else>
        <PopoverRoot v-model:open="desktopOpen" :modal="false">
            <PopoverTrigger as-child @click="remember"><slot name="trigger" :open="open" :toggle="() => { open = !open; }" /></PopoverTrigger>
            <PopoverPortal v-if="!phone">
                <PopoverContent data-kit-popover tabindex="-1" :aria-label="title" :side-offset="8" :collision-padding="{ top: headerBottom, bottom: 16, left: 16, right: 16 }" side="bottom" sticky="always" class="z-50 max-h-[min(90dvh,var(--reka-popover-content-available-height))] w-[calc(100vw-2rem)] max-w-120 min-w-0 overflow-x-hidden overflow-y-auto wrap-anywhere rounded-lg border border-border bg-popover p-4 text-popover-foreground shadow-lg focus-visible:ring-2 focus-visible:ring-ring" @escape-key-down="close" @open-auto-focus="focusPanel" @close-auto-focus="(event: Event) => { event.preventDefault(); opener?.focus(); }" @click="(event: MouseEvent) => { if ((event.target as HTMLElement).closest('a')) close(); }">
                    <div class="-mt-1 -mr-2 mb-3 flex items-start gap-2"><h2 class="min-w-0 flex-1 pt-1 wrap-anywhere font-semibold">{{ title }}</h2><button type="button" aria-label="Close working" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring" @click="close"><X class="size-4" aria-hidden="true" /></button></div><slot />
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
        <template v-if="phone">
            <!-- Reka supplies the anchor semantics; the phone opener hands off to the kit Modal. -->
            <Modal v-model:open="sheetOpen" :title="title" size="sm" placement="drawer" @closed="close(); returnFocus()"><div ref="sheet" data-kit-sheet tabindex="-1" class="outline-none" @click="(event: MouseEvent) => { if ((event.target as HTMLElement).closest('a')) close(); }"><slot /></div></Modal>
        </template>
    </template>
</template>
