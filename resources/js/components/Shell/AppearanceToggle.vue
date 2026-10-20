<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

/*
 * A light/dark switch for the header and the drawer. It reads and writes the same appearance setting as the account menu's
 * choice (the composable, with its cookie and storage), so "System" stays the default and stays choosable there; clicking
 * this moves off System to the opposite of what is showing. A real button: Enter and Space work, it is named, and
 * `aria-pressed` says whether dark is on. The class is set before paint by the head script, so a reload does not flash.
 */
const { resolvedAppearance, updateAppearance } = useAppearance();

function toggle() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <button
        type="button"
        class="flex min-h-11 min-w-11 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        aria-label="Dark mode"
        :aria-pressed="resolvedAppearance === 'dark'"
        data-appearance-toggle
        @click="toggle"
    >
        <Sun v-if="resolvedAppearance === 'dark'" class="size-4" aria-hidden="true" />
        <Moon v-else class="size-4" aria-hidden="true" />
    </button>
</template>
