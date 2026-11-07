<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

// The current workspace's pages, with one active mark: aria-current plus an underline on the bar's edge.
defineProps<{ navigation: Tey.Kit.Features.Shell.Data.ShellData['navigation']; label: string }>();
</script>
<!-- The current page: full-strength text on a tinted pill and a 3px marker under it, so it stands apart from the 70% links whatever the brand marker's colour. -->
<template>
    <nav v-if="navigation.length" :aria-label="label" class="flex items-center gap-0.5" data-shell-nav>
        <Link
            v-for="item in navigation"
            :key="item.url"
            :href="item.url"
            :aria-current="item.current ? 'page' : undefined"
            :class="['relative flex min-h-11 items-center rounded-md px-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-shell-bar-foreground/40 focus-visible:outline-none', item.current ? 'bg-shell-bar-foreground/10 text-shell-bar-foreground after:absolute after:inset-x-3 after:-bottom-2.5 after:h-[3px] after:rounded-full after:bg-[var(--brand-marker,var(--shell-bar-foreground))]' : 'text-shell-bar-foreground/70 hover:bg-shell-bar-foreground/10 hover:text-shell-bar-foreground']"
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
