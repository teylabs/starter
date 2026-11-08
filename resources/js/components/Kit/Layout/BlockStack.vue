<script setup lang="ts">
import { type PrimitiveProps } from 'reka-ui';
import { type HTMLAttributes } from 'vue';
import Stack from './Stack.vue';

// DEPRECATED: BlockStack is now Stack, with the same props and markup. This alias renders a Stack and is removed in the next release; import Stack instead.
const props = withDefaults(
    defineProps<PrimitiveProps & { class?: HTMLAttributes['class'] }>(),
    { as: 'div', class: undefined },
);
if (import.meta.env?.DEV) {
    console.warn('<BlockStack> is deprecated: use <Stack>, which it renders.');
}
</script>
<template>
    <Stack :as="props.as" :as-child="props.asChild" :class="props.class"
        ><slot
    /></Stack>
</template>
