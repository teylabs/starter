<script setup lang="ts">
import Surface from '@/components/Kit/Surface/Surface.vue';

// A card (docs/cards.md): a Surface with no padding, whose parts (CardHeader, CardBody, CardFooter, CardBanner) own their gutters and are divided by a rule.
// `as` is its element (`section` by default, so a CardHeader's heading names it).
withDefaults(defineProps<{ as?: string }>(), { as: 'section' });
</script>
<template>
    <Surface :as="as" padding="none" class="divide-border divide-y" data-card
        ><slot
    /></Surface>
</template>
