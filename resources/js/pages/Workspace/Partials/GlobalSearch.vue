<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button, SearchBox, Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger, opensSearch } from '@kit/ui';

/*
 * The header's global search, shown only when the server sends `shell.search` (the viewer may search this workspace). From `lg` up it is a field in the
 * bar; below, a button that opens it in a sheet. ⌘K (Ctrl+K), or `/` when not typing, focuses it from anywhere unless a dialog or menu is open. Any
 * navigation clears it.
 */
const props = defineProps<{ search: { url: string; label: string; placeholder: string } }>();
const wide = window.matchMedia('(min-width: 1024px)');
const isWide = ref(wide.matches);
const sheet = ref(false);
const bar = ref<InstanceType<typeof SearchBox>>();
const inSheet = ref<InstanceType<typeof SearchBox>>();

function widthChanged(): void {
    isWide.value = wide.matches;
    sheet.value = false;
}
async function focus(): Promise<void> {
    if (isWide.value) return bar.value?.focus();
    sheet.value = true;
    await nextTick();
    inSheet.value?.focus();
}
function keydown(event: KeyboardEvent): void {
    const target = event.target instanceof HTMLElement ? event.target : null;
    const typing = Boolean(target?.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"]), [role="textbox"], [role="combobox"]'));
    const overlay = [...document.querySelectorAll<HTMLElement>('[role="dialog"], [role="alertdialog"], [role="menu"]')].some((element) => element.dataset.state !== 'closed' && element.getClientRects().length > 0);
    if (!opensSearch(event, typing, overlay)) return;
    event.preventDefault();
    void focus();
}
const stop = router.on('navigate', () => (sheet.value = false));
onMounted(() => {
    window.addEventListener('keydown', keydown);
    wide.addEventListener('change', widthChanged);
});
onBeforeUnmount(() => {
    stop();
    window.removeEventListener('keydown', keydown);
    wide.removeEventListener('change', widthChanged);
});
</script>
<template>
    <div class="min-w-0" data-global-search>
        <div v-if="isWide" class="relative w-56 xl:w-72">
            <SearchBox ref="bar" :url="props.search.url" :label="props.search.label" :placeholder="props.search.placeholder" />
            <kbd aria-hidden="true" class="pointer-events-none absolute top-2 right-8 hidden rounded border border-border bg-muted px-1 text-xs text-muted-foreground xl:block">⌘K</kbd>
        </div>
        <Sheet v-else v-model:open="sheet">
            <SheetTrigger as-child>
                <Button type="button" variant="secondary" class="size-10 px-0" :aria-label="props.search.label" data-search-trigger><Search class="size-4" aria-hidden="true" /></Button>
            </SheetTrigger>
            <SheetContent side="top" class="px-4 py-6" data-search-sheet @open-auto-focus.prevent="inSheet?.focus()">
                <SheetTitle>{{ props.search.label }}</SheetTitle>
                <SheetDescription class="sr-only">{{ props.search.placeholder }}</SheetDescription>
                <SearchBox ref="inSheet" :url="props.search.url" :label="props.search.label" :placeholder="props.search.placeholder" @navigate="sheet = false" />
            </SheetContent>
        </Sheet>
    </div>
</template>
