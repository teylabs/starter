<script setup lang="ts">
// A group of fields inside a FormCard: a legend, an optional line under it, and the group's own six-column grid.
defineProps<{ legend: string; description?: string }>();
</script>
<template>
    <fieldset class="col-span-6 grid grid-cols-6 gap-x-4 gap-y-6" data-form-fieldset>
        <legend class="col-span-6 text-sm font-semibold">{{ legend }}</legend>
        <p v-if="description" class="col-span-6 -mt-4 text-sm text-muted-foreground">{{ description }}</p>
        <slot />
    </fieldset>
</template>
