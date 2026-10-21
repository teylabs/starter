<script setup lang="ts">
import { computed } from 'vue';
import { Button as BaseButton } from '@/components/ui/button';

// The three buttons a form needs: primary, secondary and destructive, and `bar`: a ghost for controls ON the shell's header bar, drawn from `--shell-bar-foreground` so it reads on a dark bar too. A submit button by default.
const props = withDefaults(defineProps<{ variant?: 'primary' | 'secondary' | 'destructive' | 'bar'; type?: 'submit' | 'button' }>(), { variant: 'primary', type: 'submit' });
const base = computed(() => (props.variant === 'primary' ? 'default' : props.variant));
</script>
<template>
    <BaseButton :variant="base" :type="type"><slot /></BaseButton>
</template>
