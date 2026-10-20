<script setup lang="ts">
import FormActions from './FormActions.vue';

// A card for a form: an optional title and description, a six-column grid for the fields (each Field takes a `span`), fieldsets inside it, and the
// FormActions bar in its footer. The submit and Cancel wording pass through (`submit-label`, `busy-label`, `cancel`, `cancel-url`); `actions` replaces the bar.
withDefaults(defineProps<{ title?: string; description?: string; submitLabel?: string; busyLabel?: string; savedLabel?: string; cancelUrl?: string; cancel?: boolean; sticky?: boolean; warnOnLeave?: boolean; form?: object }>(), { title: undefined, description: undefined, submitLabel: 'Save', busyLabel: 'Saving…', savedLabel: 'Saved', cancelUrl: undefined, cancel: true, sticky: true, warnOnLeave: true, form: undefined });
</script>
<template>
    <section class="rounded-xl border bg-card text-card-foreground shadow-xs" data-form-card>
        <header v-if="title" class="border-b px-6 py-4">
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
        </header>
        <div class="grid grid-cols-6 gap-x-4 gap-y-6 p-6 *:col-span-6"><slot /></div>
        <slot name="actions">
            <FormActions :form="form as never" :submit-label="submitLabel" :busy-label="busyLabel" :saved-label="savedLabel" :cancel-url="cancelUrl" :cancel="cancel" :sticky="sticky" :warn-on-leave="warnOnLeave"><template v-if="$slots.status" #status="slotProps"><slot name="status" v-bind="slotProps" /></template><template v-if="$slots.submit" #submit><slot name="submit" /></template></FormActions>
        </slot>
    </section>
</template>
