<script setup lang="ts">
import type { ShellLink } from './types';

/*
 * Quiet: the product, a help link, and outside production a badge naming the
 * environment. Never a second nav.
 */
defineProps<{
    product: string;
    links: ShellLink[];
    environment: string | null;
}>();
</script>

<template>
    <footer class="border-t border-border">
        <div
            class="mx-auto flex max-w-2k flex-wrap items-center gap-x-4 gap-y-2 px-4 py-6 text-xs text-muted-foreground sm:px-6 lg:px-8"
        >
            <span>© 2026 {{ product }}</span>
            <a
                v-for="link in links"
                :key="link.url"
                :href="link.url"
                class="underline-offset-4 hover:text-foreground hover:underline"
            >
                {{ link.label }}
            </a>
            <span
                v-if="environment"
                class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-warning-100 px-2 py-0.5 font-medium text-warning-900 ring-1 ring-warning-300 ring-inset dark:bg-warning-950 dark:text-warning-200 dark:ring-warning-800"
                data-shell-environment
            >
                <span
                    aria-hidden="true"
                    class="size-1.5 rounded-full bg-warning-500"
                />
                <span
                    ><span class="capitalize">{{ environment }}</span>
                    environment</span
                >
            </span>
        </div>
    </footer>
</template>
