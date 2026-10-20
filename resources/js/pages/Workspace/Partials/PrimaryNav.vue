<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

// The current workspace's pages, with one active mark: aria-current plus an underline on the bar's edge.
defineProps<{ navigation: Tey.Kit.Features.SessionUi.Data.ShellData['navigation']; label: string }>();
</script>
<template>
    <nav v-if="navigation.length" :aria-label="label" class="flex items-center gap-0.5" data-shell-nav>
        <Link
            v-for="item in navigation"
            :key="item.url"
            :href="item.url"
            :aria-current="item.current ? 'page' : undefined"
            :class="['relative flex min-h-11 items-center rounded-md px-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', item.current ? 'text-foreground after:absolute after:inset-x-3 after:-bottom-2.5 after:h-0.5 after:rounded-full after:bg-[var(--brand-marker,var(--foreground))]' : 'text-muted-foreground hover:bg-accent hover:text-foreground']"
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
