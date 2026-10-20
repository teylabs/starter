<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { useId } from 'vue';
import type { ShellNavGroup } from './types';

/*
 * The same items as the header nav, stacked, with each group's name above it.
 * The current item has a fill, a heavier weight and a bar at its edge; hover
 * is a lighter fill only, so the two never look alike.
 */
defineProps<{ navigation: ShellNavGroup[] }>();

defineEmits<{ navigate: [] }>();

const id = useId();
</script>

<template>
    <nav aria-label="Primary" class="flex flex-col gap-6">
        <div v-for="(group, index) in navigation" :key="group.label">
            <p
                :id="`${id}-${index}`"
                class="px-3 pb-1 text-xs font-medium text-muted-foreground"
            >
                {{ group.label }}
            </p>
            <ul
                :aria-labelledby="`${id}-${index}`"
                class="flex flex-col gap-0.5"
            >
                <li v-for="item in group.items" :key="item.url">
                    <Link
                        :href="item.url"
                        :aria-current="item.current ? 'page' : undefined"
                        class="relative flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-sidebar-accent/50 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-[current=page]:bg-sidebar-accent aria-[current=page]:font-semibold aria-[current=page]:text-foreground aria-[current=page]:before:absolute aria-[current=page]:before:inset-y-2.5 aria-[current=page]:before:left-0 aria-[current=page]:before:w-0.5 aria-[current=page]:before:rounded-full aria-[current=page]:before:bg-brand-600 dark:aria-[current=page]:before:bg-brand-400"
                        @click="$emit('navigate')"
                    >
                        <span class="flex-1">{{ item.label }}</span>
                        <template v-if="item.state === 'locked'">
                            <Lock class="size-3.5" aria-hidden="true" />
                            <span class="sr-only">(not on your plan)</span>
                        </template>
                    </Link>
                </li>
            </ul>
        </div>
    </nav>
</template>
