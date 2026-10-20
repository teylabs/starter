<script setup lang="ts">
import { useFormContext } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Label } from '@/components/ui/label';

// A labelled field: the label, the control in the default slot, an optional description, and the error as an alert. Inside Inertia's <Form> the error is
// the form's `errors[name]`; `error` passes one explicitly. `span` is how many of a FormCard's six columns it takes (a whole row by default, from the card; the wider
// widths apply from `sm` up); outside a card there is no grid and no span. The control takes `id` and, to be read with them, `aria-describedby="<id>-description <id>-error"`.
const props = withDefaults(defineProps<{ id: string; label: string; name?: string; description?: string; error?: string; span?: 1 | 2 | 3 | 4 | 5 | 6 }>(), { name: undefined, description: undefined, error: undefined, span: undefined });
const context = useFormContext() as { errors?: Record<string, string> } | undefined;
const message = computed(() => props.error ?? (props.name ? context?.errors?.[props.name] : undefined));
// Whole class strings, never built from the prop (Tailwind finds classes by scanning the source).
const spans = { 1: 'col-span-6 sm:col-span-1', 2: 'col-span-6 sm:col-span-2', 3: 'col-span-6 sm:col-span-3', 4: 'col-span-6 sm:col-span-4', 5: 'col-span-6 sm:col-span-5', 6: 'col-span-6' } as const;
</script>
<template>
    <div :class="['grid content-start gap-2', span && spans[span]]">
        <Label :for="id">{{ label }}</Label>
        <slot />
        <p v-if="description" :id="`${id}-description`" class="text-sm text-muted-foreground">{{ description }}</p>
        <p v-if="message" :id="`${id}-error`" role="alert" class="text-sm text-destructive">{{ message }}</p>
    </div>
</template>
