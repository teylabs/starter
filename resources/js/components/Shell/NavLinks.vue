<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import type { ShellNavGroup } from './types';

/*
 * The header's primary nav: the groups sit together beside the mark, in the
 * server's order, with a hairline between groups; the first, the user's
 * workspaces, opens with a "Workspaces" link styled like the rest. Each link carries its own
 * padding for a 44px target, and the current page has aria-current plus an
 * underline on the bar's edge, which hover and focus never draw. The nav
 * never wraps: below `lg` it moves into the menu sheet.
 */
defineProps<{ navigation: ShellNavGroup[] }>();

// One look for every link in the bar, the "Workspaces" entry included.
const linkClass = 'relative flex min-h-11 items-center gap-1.5 rounded-md px-3 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-[current=page]:text-foreground aria-[current=page]:after:absolute aria-[current=page]:after:inset-x-3 aria-[current=page]:after:-bottom-2.5 aria-[current=page]:after:h-0.5 aria-[current=page]:after:rounded-full aria-[current=page]:after:bg-brand-600 dark:aria-[current=page]:after:bg-brand-400';
</script>

<template>
    <nav aria-label="Primary" class="flex items-center" data-shell-nav>
        <template v-for="(group, index) in navigation" :key="group.label">
            <span
                v-if="index > 0"
                aria-hidden="true"
                class="mx-2 h-5 w-px bg-border"
            />
            <Link v-if="index === 0" href="/workspaces" :class="linkClass">{{ group.label }}</Link>
            <ul :aria-label="group.label" class="flex items-center gap-0.5">
                <li v-for="item in group.items" :key="item.url">
                    <Link
                        :href="item.url"
                        :aria-current="item.current ? 'page' : undefined"
                        :class="linkClass"
                    >
                        {{ item.label }}
                        <template v-if="item.state === 'locked'">
                            <Lock class="size-3.5" aria-hidden="true" />
                            <span class="sr-only">(not on your plan)</span>
                        </template>
                    </Link>
                </li>
            </ul>
        </template>
    </nav>
</template>
