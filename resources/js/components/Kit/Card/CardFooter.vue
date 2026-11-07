<script setup lang="ts">
import { CARD_GUTTER } from './cardTone';

// A card's footer: compact, in the card's gutters. `sticky` keeps it at the bottom of a scrolling card (turn the Card's clip off for that).
withDefaults(defineProps<{ sticky?: boolean }>(), { sticky: false });
</script>
<template>
    <footer
        :class="[
            CARD_GUTTER,
            'py-2.5 text-sm',
            sticky && 'bg-muted sticky bottom-0',
        ]"
        data-card-footer
    >
        <slot />
    </footer>
</template>
