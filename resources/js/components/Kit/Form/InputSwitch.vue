<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import { customProperties } from './inputSelect';
import { useLocked } from './useLocked';
import { useFieldAria } from './fieldAria';

// An on/off switch with its label beside it, in the default slot: InputCheckbox's API (id, name, value, disabled, invalid, description, v-model, attributes) on
// Reka UI's SwitchRoot (the host's shadcn-vue set has no Switch). `id` is required: the label points at it (`for`), so clicking the label toggles it, and so does a Field's error.
// It is a `role="switch"` button with `aria-checked`; Space and Enter toggle it. With `name` inside a form it submits as a checkbox does (`name=on`, or `value`, while on;
// nothing while off); without `name` it submits nothing.
// Disabled keeps its label and description at full contrast: the track is muted and the cursor `not-allowed`, with no opacity.
// Inside a locked LockedForm it is read-only, as InputCheckbox is: `aria-readonly`, still in the Tab order, and click, Space, Enter and the label leave it unchanged.
// Caller attributes land on the switch itself; `class` stays on the row, and only the custom properties (`--*`) of a `style`. `invalid` sets `aria-invalid`.
// `description` shows under the label as `<id>-hint` and joins the switch's `aria-describedby`, after any the caller gives.
defineOptions({ inheritAttrs: false });
const props = defineProps<{
    id: string;
    name?: string;
    value?: string;
    disabled?: boolean;
    invalid?: boolean;
    description?: string;
}>();
const model = defineModel<boolean>({ default: false });
const attrs = useAttrs();
const lock = useLocked();
const locked = computed(() => lock.locked && !props.disabled);
const controlAttrs = computed(() => {
    const rest: Record<string, unknown> = { ...attrs };
    delete rest.class;
    delete rest.style;
    return rest;
});
const set = (value: unknown) => value !== undefined && value !== false;
const aria = useFieldAria(attrs, () => props.invalid);
const describedBy = computed(() => {
    const ids = [
        ...String(aria.value['aria-describedby'] ?? '').split(/\s+/),
        props.description ? `${props.id}-hint` : '',
    ].filter(Boolean);
    return ids.length ? [...new Set(ids)].join(' ') : undefined;
});
// While locked, cancel what would toggle it: a click (the switch's, its label's, or the one a browser makes from Space) and Space or Enter themselves, on keydown
// and keyup, so no browser's key activation reaches SwitchRoot. Other keys (Tab) pass.
const toggleKeys = new Set([' ', 'Spacebar', 'Enter']);
function guard(event: Event) {
    if (!locked.value) return;
    if (event instanceof KeyboardEvent && !toggleKeys.has(event.key)) return;
    event.preventDefault();
    event.stopPropagation();
}
</script>
<template>
    <div
        :class="['grid gap-1', attrs.class]"
        :style="customProperties(attrs.style)"
        @click.capture="guard"
        @keydown.capture="guard"
        @keyup.capture="guard"
    >
        <div class="flex items-center gap-2">
            <SwitchRoot
                v-bind="controlAttrs"
                :id="id"
                v-model="model"
                :name="name"
                :value="value"
                :disabled="disabled"
                :required="set(attrs.required) || undefined"
                :aria-required="
                    set(attrs.required)
                        ? 'true'
                        : (attrs['aria-required'] as
                              'true' | 'false' | undefined)
                "
                :aria-invalid="aria['aria-invalid']"
                :aria-describedby="describedBy"
                :aria-readonly="locked || undefined"
                :data-locked="locked || undefined"
                data-slot="switch"
                :class="[
                    'peer border-input data-[state=checked]:bg-primary data-[state=unchecked]:bg-input dark:data-[state=unchecked]:bg-input/80 focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 disabled:data-[state=checked]:bg-muted-foreground disabled:data-[state=unchecked]:bg-muted inline-flex h-5 w-9 shrink-0 items-center rounded-full border shadow-xs transition-all outline-none focus-visible:ring-3 disabled:cursor-not-allowed disabled:shadow-none',
                    locked && 'cursor-default',
                ]"
            >
                <SwitchThumb
                    data-slot="switch-thumb"
                    class="bg-background dark:data-[state=unchecked]:bg-foreground pointer-events-none block size-4 rounded-full shadow-sm ring-0 transition-transform data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0.5"
                />
            </SwitchRoot>
            <label
                :for="id"
                :class="[
                    'text-sm leading-none',
                    disabled && 'cursor-not-allowed',
                    locked && 'cursor-default',
                ]"
                ><slot
            /></label>
        </div>
        <p
            v-if="description"
            :id="`${id}-hint`"
            class="text-muted-foreground pl-11 text-sm"
        >
            {{ description }}
        </p>
    </div>
</template>
