<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
type ShellLink = { label: string; url: string };

/* Above the title, only when the server sends two or more levels. */
defineProps<{ breadcrumbs: ShellLink[] }>();
</script>

<template>
    <nav v-if="breadcrumbs.length > 1" aria-label="Breadcrumb" class="mb-3">
        <ol
            class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground"
        >
            <li
                v-for="(crumb, index) in breadcrumbs"
                :key="crumb.url"
                class="flex items-center gap-1"
            >
                <ChevronRight
                    v-if="index > 0"
                    class="size-3.5"
                    aria-hidden="true"
                />
                <Link
                    v-if="index < breadcrumbs.length - 1"
                    :href="crumb.url"
                    class="hover:text-foreground hover:underline"
                >
                    {{ crumb.label }}
                </Link>
                <span v-else aria-current="page" class="text-foreground">
                    {{ crumb.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>
