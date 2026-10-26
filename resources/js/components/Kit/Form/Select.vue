<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import { nextTick, onMounted, ref, watch } from 'vue';
import { SelectContent, SelectItem, SelectItemIndicator, SelectItemText, SelectPortal, SelectRoot, SelectTrigger, SelectValue, SelectViewport } from 'reka-ui';

// Deprecated: use InputSelect, the kit's rich select. Select stays, unchanged, until 0.4 (its props don't map one to one: `autofocus`, and InputSelect turns search on past 8 options).
// A select on Reka UI: `options` are values with labels; `id` goes on the trigger and `name` makes it submit a native named value, through a hidden input (so it works inside an uncontrolled <Form>), so a Field's label points at it.
const props = defineProps<{ id?: string; name?: string; options: { value: string; label: string }[]; placeholder?: string; disabled?: boolean; invalid?: boolean; autofocus?: boolean }>();
// `autofocus` focuses the trigger when the page arrives.
const trigger = ref<{ $el?: HTMLElement } | null>(null);
onMounted(() => props.autofocus && trigger.value?.$el?.focus());
const model = defineModel<string>();
// Reka's own native select (made from `name`) is only filled once the options have mounted, so inside an uncontrolled <Form> a control that was never opened
// submits nothing. A hidden input mirrors the value instead, and tells the form it changed.
const native = ref<HTMLInputElement | null>(null);
watch(model, async () => {
    await nextTick();
    native.value?.dispatchEvent(new Event('input', { bubbles: true }));
});
</script>
<template>
    <SelectRoot v-model="model" :disabled="disabled">
        <input v-if="name" ref="native" type="hidden" :name="name" :value="model ?? ''" :disabled="disabled" />
        <SelectTrigger
            ref="trigger"
            :id="id"
            :aria-invalid="invalid || undefined"
            class="flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 data-[placeholder]:text-muted-foreground dark:bg-input/30"
        >
            <SelectValue :placeholder="placeholder" />
            <ChevronDown class="size-4 text-muted-foreground" aria-hidden="true" />
        </SelectTrigger>
        <SelectPortal>
            <SelectContent position="popper" :side-offset="4" class="z-50 max-h-72 min-w-(--reka-select-trigger-width) overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md">
                <SelectViewport class="p-1">
                    <SelectItem v-for="option in options" :key="option.value" :value="option.value" class="relative flex cursor-pointer data-[disabled]:cursor-not-allowed items-center rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground">
                        <SelectItemText>{{ option.label }}</SelectItemText>
                        <SelectItemIndicator class="absolute right-2 flex items-center"><Check class="size-4" aria-hidden="true" /></SelectItemIndicator>
                    </SelectItem>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
