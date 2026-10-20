<script setup lang="ts">
import { Label } from '@/components/ui/label';

// A labelled field: the label, the control in the default slot, an optional description, and the error as an alert.
// The control takes `id` and, to be read with them, `aria-describedby="<id>-description <id>-error"`.
defineProps<{ id: string; label: string; description?: string; error?: string }>();
</script>
<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>
        <slot />
        <p v-if="description" :id="`${id}-description`" class="text-sm text-muted-foreground">{{ description }}</p>
        <p v-if="error" :id="`${id}-error`" role="alert" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
