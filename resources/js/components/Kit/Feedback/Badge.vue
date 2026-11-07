<script setup lang="ts">
/*
 * A short status label (a table's status cell, a count): `tone` neutral, info, success, warning or danger, on the kit's tone tokens (the same pale background and strong
 * foreground the toasts use, so each meets AA, light and dark), and `size` sm or md. The text is the status: a colour never carries meaning alone. Not a live region
 * and not interactive; for a removable chip, use a button. Utilities and tokens only.
 */
withDefaults(
    defineProps<{
        tone?: 'neutral' | 'info' | 'success' | 'warning' | 'danger';
        size?: 'sm' | 'md';
    }>(),
    { tone: 'neutral', size: 'sm' },
);
// Whole class strings, never built from the prop (Tailwind finds classes by scanning the source).
const tones = {
    neutral: 'border-border bg-muted text-muted-foreground',
    info: 'border-(--kit-tone-info-border) bg-(--kit-tone-info-bg) text-(--kit-tone-info-fg)',
    success:
        'border-(--kit-tone-success-border) bg-(--kit-tone-success-bg) text-(--kit-tone-success-fg)',
    warning:
        'border-(--kit-tone-warning-border) bg-(--kit-tone-warning-bg) text-(--kit-tone-warning-fg)',
    danger: 'border-(--kit-tone-error-border) bg-(--kit-tone-error-bg) text-(--kit-tone-error-fg)',
} as const;
const sizes = { sm: 'h-5 px-1.5 text-xs', md: 'h-6 px-2 text-sm' } as const;
</script>
<template>
    <span
        :class="[
            'inline-flex max-w-full shrink-0 items-center gap-1 rounded-md border font-medium whitespace-nowrap',
            tones[tone],
            sizes[size],
        ]"
        :data-tone="tone"
        data-badge
        ><span class="truncate"><slot /></span
    ></span>
</template>
