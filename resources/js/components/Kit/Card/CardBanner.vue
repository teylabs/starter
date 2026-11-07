<script setup lang="ts">
import { CARD_TONES, type CardTone } from './cardTone';

// A short centred band at the top of a card that draws the eye (an action centre, an error, done). Its words carry the meaning, never the colour alone.
withDefaults(defineProps<{ tone?: CardTone }>(), { tone: 'muted' });
</script>
<template>
    <div
        :class="[
            'px-4 py-2 text-center text-sm font-semibold',
            CARD_TONES[tone],
        ]"
        data-card-banner
        :data-tone="tone"
    >
        <slot />
    </div>
</template>
