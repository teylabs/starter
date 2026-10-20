<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { Toggle } from 'reka-ui';
import { useAppearance } from './useAppearance';

/*
 * A light/dark switch for the header and the drawer, on Reka's Toggle (a real button: Enter and Space work, it is named, and it says whether dark is on).
 * It reads and writes the same appearance setting as AppearanceChoice (cookie and storage), so "System" stays the default and stays choosable there;
 * pressing this moves off System to the opposite of what is showing. The class is set before paint by the head script, so a reload does not flash.
 */
const { resolvedAppearance, updateAppearance } = useAppearance();
</script>
<template>
    <Toggle
        :model-value="resolvedAppearance === 'dark'"
        aria-label="Dark mode"
        class="flex min-h-11 min-w-11 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        data-appearance-toggle
        @update:model-value="updateAppearance($event ? 'dark' : 'light')"
    >
        <Sun v-if="resolvedAppearance === 'dark'" class="size-4" aria-hidden="true" />
        <Moon v-else class="size-4" aria-hidden="true" />
    </Toggle>
</template>
