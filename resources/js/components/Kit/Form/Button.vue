<script setup lang="ts">
import { computed } from 'vue';
import { Button as BaseButton } from '@/components/ui/button';
import { inverseControl, inverseFilled, pillControl, useSurface } from '@/components/Kit/Surface/surface';

// The three buttons a form needs: primary, secondary and destructive, and `bar`: a ghost for controls ON the shell's header bar, drawn from `--shell-bar-foreground` so it reads on a dark bar too. It uses the host button's standard `ghost` variant, so the host needs no extra variant. A submit button by default.
// On a surface (FloatingToolbar's pill) it takes that surface's shape and tone; elsewhere the shared radius and tones hold.
const props = withDefaults(defineProps<{ variant?: 'primary' | 'secondary' | 'destructive' | 'bar'; type?: 'submit' | 'button' }>(), { variant: 'primary', type: 'submit' });
const base = computed(() => (props.variant === 'primary' ? 'default' : props.variant === 'bar' ? 'ghost' : props.variant));
const barClass = 'text-[color:var(--shell-bar-foreground)] hover:bg-[color:var(--shell-bar-foreground)]/10 hover:text-[color:var(--shell-bar-foreground)]';
const surface = useSurface();
const blend = computed(() => [
    props.variant === 'bar' && barClass,
    surface.value.shape === 'pill' && pillControl,
    surface.value.tone === 'inverse' && (props.variant === 'primary' ? inverseFilled : inverseControl),
]);
</script>
<template>
    <BaseButton :variant="base" :type="type" :class="blend"><slot /></BaseButton>
</template>
