<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';

/*
 * The page measure: a centred column at one of a few fixed widths, inside the shell. The shell owns the gutters and the vertical rhythm,
 * so this adds no padding. Use it where the content is one sequence read top to bottom (a form, a settings section, a confirmation);
 * dashboards and lists take the full width and need no wrapper.
 *
 * The lookup is STATIC and must stay that way: Tailwind finds class names by scanning source text, so a class built from the prop
 * (`max-w-${size}`) does not exist at build time and silently renders no width. Whole class strings only.
 */
type FocusPageSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl';

const props = withDefaults(defineProps<{ size?: FocusPageSize; class?: HTMLAttributes['class'] }>(), { size: 'lg' });

const maxWidths: Record<FocusPageSize, string> = {
    xs: 'max-w-2xl',
    sm: 'max-w-3xl',
    md: 'max-w-5xl',
    lg: 'max-w-7xl',
    xl: 'max-w-[96rem]',
};

const maxWidthClass = computed(() => maxWidths[props.size]);
</script>
<template>
    <div data-slot="focus-page" :class="['mx-auto w-full', maxWidthClass, props.class]">
        <slot />
    </div>
</template>
