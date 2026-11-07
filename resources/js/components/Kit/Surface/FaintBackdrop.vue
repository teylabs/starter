<script setup lang="ts">
/*
 * A mark as a faint ground behind content (docs/surfaces.md, "Faint backdrop"): a flat colour seen through a mask of single-colour artwork, so one asset
 * serves light and dark. It is decoration: hidden from assistive tech, takes no pointer or selection, and never prints.
 * Its look is four CSS variables the app defines, in its stylesheet per scheme or on the element as arbitrary-property classes:
 * `--faint-backdrop-image` (a url() whose alpha is the shape), `--faint-backdrop-ink` (default currentColor), `--faint-backdrop-opacity` (default 0.05),
 * and for `tile` `--faint-backdrop-tile` (the repeat size, default 180px). Keeping them tokens lets the app test text contrast over the backdrop as arithmetic.
 * `placement`: `bleed` (oversized, cropped by the bottom-right edge, fixed), `corner` (small, bottom-right, fixed), `inset` (centred in a positioned
 * panel) or `tile` (a repeating ground, fixed).
 */
withDefaults(
    defineProps<{
        placement?: 'bleed' | 'corner' | 'inset' | 'tile';
    }>(),
    { placement: 'bleed' },
);

const placements = {
    bleed: 'fixed -right-[18vmin] -bottom-[14vmin] -z-10 size-[110vmin] [mask-size:contain] [mask-repeat:no-repeat]',
    corner: 'fixed right-6 bottom-6 -z-10 size-40 [mask-size:contain] [mask-repeat:no-repeat]',
    inset: 'absolute inset-x-0 top-1/2 z-0 mx-auto size-64 -translate-y-1/2 transition-opacity duration-500 [mask-size:contain] [mask-repeat:no-repeat]',
    tile: 'fixed inset-0 -z-10 [mask-size:var(--faint-backdrop-tile,180px)] [mask-repeat:repeat]',
} as const;
</script>

<template>
    <div
        aria-hidden="true"
        data-faint-backdrop
        :data-placement="placement"
        :class="[
            'pointer-events-none bg-(--faint-backdrop-ink,currentColor) [mask-image:var(--faint-backdrop-image)] [mask-position:center] opacity-(--faint-backdrop-opacity,0.05) select-none print:hidden',
            placements[placement],
        ]"
    />
</template>
