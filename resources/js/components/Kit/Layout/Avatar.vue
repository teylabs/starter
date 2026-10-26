<script setup lang="ts">
// A person's photo, or their initials when they have none. `src2x` is the retina source. Decorative by default (the name is beside it).
defineProps<{ initials: string; src?: string | null; src2x?: string | null; large?: boolean }>();
</script>
<template>
    <span aria-hidden="true" :class="['flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-accent font-semibold text-accent-foreground', large ? 'size-20 text-xl' : 'size-8 text-xs']" data-slot="avatar">
        <img v-if="src" :src="src" :srcset="src2x ? `${src} 1x, ${src2x} 2x` : undefined" alt="" class="size-full object-cover" />
        <template v-else>{{ initials }}</template>
    </span>
</template>
