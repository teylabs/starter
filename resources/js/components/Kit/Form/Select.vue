<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import { SelectContent, SelectItem, SelectItemIndicator, SelectItemText, SelectPortal, SelectRoot, SelectTrigger, SelectValue, SelectViewport } from 'reka-ui';

// A select on Reka UI: `options` are values with labels; `id` goes on the trigger, so a Field's label points at it.
defineProps<{ id?: string; options: { value: string; label: string }[]; placeholder?: string; disabled?: boolean; invalid?: boolean }>();
const model = defineModel<string>();
</script>
<template>
    <SelectRoot v-model="model" :disabled="disabled">
        <SelectTrigger
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
                    <SelectItem v-for="option in options" :key="option.value" :value="option.value" class="relative flex cursor-default items-center rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground">
                        <SelectItemText>{{ option.label }}</SelectItemText>
                        <SelectItemIndicator class="absolute right-2 flex items-center"><Check class="size-4" aria-hidden="true" /></SelectItemIndicator>
                    </SelectItem>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
