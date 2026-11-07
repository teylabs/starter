<script setup lang="ts">
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import { computed, provide } from 'vue';
import { FIELD_ARIA } from './fieldAria';
import { Label } from '@/components/ui/label';

// A labelled field: the label, the control in the default slot, an optional description, and the error as an alert. Inside Inertia's <Form> the error is
// the form's `errors[name]`; `error` passes one explicitly. `span` is how many of a FormCard's six columns it takes (a whole row by default, from the card; the wider
// widths apply from `sm` up); outside a card there is no grid and no span. The control takes `id`; every Kit/Form control then reads this field (fieldAria) for `aria-describedby`, naming only the description and error actually rendered, and `aria-invalid` while there is an error. The label is `<id>-label`, for a group control (RadioCardGroup) to name itself by.
const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        name?: string;
        description?: string;
        error?: string;
        span?: 1 | 2 | 3 | 4 | 5 | 6;
    }>(),
    {
        name: undefined,
        description: undefined,
        error: undefined,
        span: undefined,
    },
);
const context = useOptionalFormContext() as
    { errors?: Record<string, string> } | undefined;
const message = computed(
    () =>
        props.error ?? (props.name ? context?.errors?.[props.name] : undefined),
);
// Whole class strings, never built from the prop (Tailwind finds classes by scanning the source).
// The ids the control is described by: only nodes this field renders, so none dangles.
provide(FIELD_ARIA, {
    id: props.id,
    describedBy: computed(() =>
        [
            props.description ? `${props.id}-description` : '',
            message.value ? `${props.id}-error` : '',
        ].filter(Boolean),
    ),
    invalid: computed(() => Boolean(message.value)),
});
const spans = {
    1: 'col-span-6 sm:col-span-1',
    2: 'col-span-6 sm:col-span-2',
    3: 'col-span-6 sm:col-span-3',
    4: 'col-span-6 sm:col-span-4',
    5: 'col-span-6 sm:col-span-5',
    6: 'col-span-6',
} as const;
</script>
<template>
    <div :class="['grid content-start gap-2', span && spans[span]]">
        <Label :id="`${id}-label`" :for="id">{{ label }}</Label>
        <slot />
        <p
            v-if="description"
            :id="`${id}-description`"
            class="text-muted-foreground text-sm"
        >
            {{ description }}
        </p>
        <p
            v-if="message"
            :id="`${id}-error`"
            role="alert"
            class="text-destructive-text text-sm"
        >
            {{ message }}
        </p>
    </div>
</template>
