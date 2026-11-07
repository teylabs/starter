<script setup lang="ts">
import { CARD_GUTTER } from './cardTone';

// A card's content, in the card's gutters.
</script>
<template>
    <div :class="[CARD_GUTTER, 'py-4']" data-card-body><slot /></div>
</template>
