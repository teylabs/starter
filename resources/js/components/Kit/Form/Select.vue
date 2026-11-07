<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, useAttrs, watch } from 'vue';
import {
    SelectContent,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';
import { useLayerTarget } from '@/components/Kit/Overlay/layer';
import { useLocked } from './useLocked';
import { useFieldAria } from './fieldAria';

// Deprecated: use InputSelect, the kit's rich select. Select stays, its props unchanged, until 0.4 (they don't map one to one: `autofocus`, and InputSelect turns search on past 8 options).
// A select on Reka UI: `options` are values with labels; `id` goes on the trigger and `name` makes it submit a native named value, through a hidden input (so it works inside an uncontrolled <Form>), so a Field's label points at it.
// Caller attributes (`aria-describedby`, `data-*` and the rest, `class` included) land on the trigger; `required` marks the select required (`aria-required` on the trigger).
defineOptions({ inheritAttrs: false });
const props = defineProps<{
    id?: string;
    name?: string;
    options: { value: string; label: string }[];
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    autofocus?: boolean;
}>();
// `autofocus` focuses the trigger when the page arrives. Inside a locked LockedForm (the LockedForm spec) the trigger stays a focusable combobox with `aria-readonly`, shows the chosen label and opens nothing; a caller's own `disabled` still wins.
const trigger = ref<{ $el?: HTMLElement } | null>(null);
onMounted(() => props.autofocus && trigger.value?.$el?.focus());
const model = defineModel<string>();
const attrs = useAttrs();
// Inside a Field: described only by what it renders, and invalid while it has an error.
const aria = useFieldAria(attrs, () => props.invalid);
const required = computed(
    () => attrs.required !== undefined && attrs.required !== false,
);
const triggerAttrs = computed(() => {
    const rest: Record<string, unknown> = { ...attrs };
    delete rest.required;
    delete rest.class;
    return rest;
});
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
const open = ref(false);
function guardKey(event: KeyboardEvent) {
    if (
        locked.value &&
        !['Tab', 'Shift', 'Escape'].includes(event.key) &&
        !event.metaKey &&
        !event.ctrlKey
    ) {
        event.preventDefault();
        event.stopPropagation();
    }
}
// Reka's own native select (made from `name`) is only filled once the options have mounted, so inside an uncontrolled <Form> a control that was never opened
// submits nothing. A hidden input mirrors the value instead, and tells the form it changed.
const native = ref<HTMLInputElement | null>(null);
watch(model, async () => {
    await nextTick();
    native.value?.dispatchEvent(new Event('input', { bubbles: true }));
});
// Inside a Modal its overlay renders into the Modal's <dialog>, over it (layer.ts).
const layerTarget = useLayerTarget();
</script>
<template>
    <SelectRoot
        v-model="model"
        :disabled="disabled"
        :required="required"
        :open="locked ? false : open"
        @update:open="open = $event"
    >
        <input
            v-if="name"
            ref="native"
            type="hidden"
            :name="name"
            :value="model ?? ''"
            :disabled="disabled"
        />
        <SelectTrigger
            ref="trigger"
            v-bind="triggerAttrs"
            :id="id"
            :aria-describedby="aria['aria-describedby']"
            :aria-invalid="aria['aria-invalid']"
            :aria-readonly="locked || undefined"
            :data-locked="locked || undefined"
            :class="[
                'focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 data-[placeholder]:text-muted-foreground flex h-9 w-full items-center justify-between gap-2 rounded-md border px-3 py-1 text-sm outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
                locked
                    ? 'border-border bg-muted cursor-default shadow-none'
                    : 'border-input dark:bg-input/30 bg-transparent shadow-xs',
                attrs.class,
            ]"
            @keydown.capture="guardKey"
        >
            <SelectValue :placeholder="locked ? undefined : placeholder" />
            <ChevronDown
                v-if="!locked"
                class="text-muted-foreground size-4"
                aria-hidden="true"
            />
        </SelectTrigger>
        <SelectPortal :to="layerTarget">
            <SelectContent
                position="popper"
                :side-offset="4"
                class="bg-popover text-popover-foreground z-50 max-h-72 min-w-(--reka-select-trigger-width) overflow-hidden rounded-md border shadow-md"
            >
                <SelectViewport class="p-1">
                    <SelectItem
                        v-for="option in options"
                        :key="option.value"
                        :value="option.value"
                        class="data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground relative flex cursor-pointer items-center rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-[disabled]:cursor-not-allowed"
                    >
                        <SelectItemText>{{ option.label }}</SelectItemText>
                        <SelectItemIndicator
                            class="absolute right-2 flex items-center"
                            ><Check class="size-4" aria-hidden="true"
                        /></SelectItemIndicator>
                    </SelectItem>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
