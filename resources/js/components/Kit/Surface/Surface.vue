<script setup lang="ts">
import { computed } from 'vue';
import { provideCard, provideSurface, type Surface } from './surface';

// A card-like container (an inspector panel, a toolbar group, a slide's frame): padding, a border, the radius and the card tokens. It provides its tone and
// shape to what is inside (provideSurface), so a kit Button or ActionBar on it blends in: `inverse` is a dark surface with light controls, `pill` a fully
// rounded one. `padding` is none, sm or md (default); `as` is its element (div by default). `clip` (on by default) clips what is inside to the radius;
// turn it off for a sticky child, which an overflow-hidden ancestor would pin in place. `translucent` lets what is behind show through (80% fill and a
// backdrop blur), but only where it stays legible and calm: solid without backdrop-filter support, under prefers-contrast: more, under
// prefers-reduced-transparency, and with no blur under prefers-reduced-motion (a blur over moving content reads as motion).
const props = withDefaults(
    defineProps<{
        tone?: Surface['tone'];
        shape?: Surface['shape'];
        padding?: 'none' | 'sm' | 'md';
        as?: string;
        clip?: boolean;
        translucent?: boolean;
    }>(),
    {
        tone: 'default',
        shape: 'default',
        padding: 'md',
        as: 'div',
        clip: true,
        translucent: false,
    },
);
provideSurface(() => ({ tone: props.tone, shape: props.shape }));
// What is inside sits on a card, so a component there draws no ground card of its own.
provideCard();
const safe =
    'motion-reduce:backdrop-blur-none contrast-more:backdrop-blur-none [@media(prefers-reduced-transparency:reduce)]:backdrop-blur-none';
const translucentDefault = `supports-[backdrop-filter]:bg-card/80 supports-[backdrop-filter]:backdrop-blur-md contrast-more:supports-[backdrop-filter]:bg-card [@media(prefers-reduced-transparency:reduce)]:supports-[backdrop-filter]:bg-card ${safe}`;
const translucentInverse = `supports-[backdrop-filter]:bg-foreground/80 supports-[backdrop-filter]:backdrop-blur-md contrast-more:supports-[backdrop-filter]:bg-foreground [@media(prefers-reduced-transparency:reduce)]:supports-[backdrop-filter]:bg-foreground ${safe}`;
const classes = computed(() => [
    'border',
    props.shape === 'pill' ? 'rounded-full' : 'rounded-xl',
    props.tone === 'inverse'
        ? 'border-foreground bg-foreground text-background'
        : 'bg-card text-card-foreground shadow-xs',
    props.translucent &&
        (props.tone === 'inverse' ? translucentInverse : translucentDefault),
    { none: 'p-0', sm: 'p-3', md: 'p-6' }[props.padding],
    props.clip && 'overflow-hidden',
]);
</script>
<template>
    <component
        :is="as"
        :class="classes"
        data-surface
        :data-tone="tone"
        :data-shape="shape"
        :data-translucent="translucent || undefined"
        ><slot
    /></component>
</template>
