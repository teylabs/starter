<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { Checkbox as BaseCheckbox } from '@/components/ui/checkbox';
import { customProperties } from './inputSelect';
import { useLocked } from './useLocked';
import { useFieldAria } from './fieldAria';

// A checkbox with its label beside it, in the default slot. `id` is required: the label points at it (`for`), and so does a Field's error.
// Inside a locked LockedForm it is read-only through the context (the LockedForm spec): `aria-readonly`, the checked state and colours kept at full contrast, still in the Tab order, and click, Space and the label leave it unchanged (the click is stopped before the control sees it). A caller's own `disabled` still wins.
// Caller attributes (`aria-describedby`, `required`, `data-*` and the rest) land on the checkbox itself; `class` stays on the row, and only the custom properties (`--*`) of a `style`. `invalid` sets `aria-invalid`.
// `description` shows under the label as `<id>-hint` (not `-description`, which is the Field's) and joins the checkbox's `aria-describedby`, after any the caller gives.
defineOptions({ inheritAttrs: false });
const props = defineProps<{
    id: string;
    name?: string;
    value?: string;
    disabled?: boolean;
    invalid?: boolean;
    description?: string;
}>();
const model = defineModel<boolean>();
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
// Inside a Field: described only by what it renders, and invalid while it has an error.
const aria = useFieldAria(attrs, () => props.invalid);
const describedBy = computed(() => {
    const ids = [
        ...String(aria.value['aria-describedby'] ?? '').split(/\s+/),
        props.description ? `${props.id}-hint` : '',
    ].filter(Boolean);
    return ids.length ? [...new Set(ids)].join(' ') : undefined;
});
function guard(event: Event) {
    if (!locked.value) return;
    event.preventDefault();
    event.stopPropagation();
}
</script>
<template>
    <div
        :class="['grid gap-1', attrs.class]"
        :style="customProperties(attrs.style)"
        @click.capture="guard"
    >
        <div class="flex items-center gap-2">
            <BaseCheckbox
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
                :class="locked && 'cursor-default'"
            />
            <label
                :for="id"
                :class="['text-sm leading-none', locked && 'cursor-default']"
                ><slot
            /></label>
        </div>
        <p
            v-if="description"
            :id="`${id}-hint`"
            class="text-muted-foreground pl-6 text-sm"
        >
            {{ description }}
        </p>
    </div>
</template>
