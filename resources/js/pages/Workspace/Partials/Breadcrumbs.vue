<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

// The trail to the page, shared by gretel: each route declares its crumb and parent, and the links are in the form the session uses.
type Crumb = { title: string; url: string; is_current_page: boolean };
const page = usePage<{ breadcrumbs?: Crumb[] }>();
const breadcrumbs = computed(() => page.props.breadcrumbs ?? []);
</script>
<template>
    <nav v-if="breadcrumbs.length > 1" aria-label="Breadcrumb" class="mb-3" data-shell-breadcrumbs>
        <ol class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
            <li v-for="(crumb, index) in breadcrumbs" :key="crumb.url" class="flex items-center gap-1">
                <ChevronRight v-if="index > 0" class="size-3.5" aria-hidden="true" />
                <Link v-if="!crumb.is_current_page" :href="crumb.url" class="hover:text-foreground hover:underline">{{ crumb.title }}</Link>
                <span v-else aria-current="page" class="text-foreground">{{ crumb.title }}</span>
            </li>
        </ol>
    </nav>
</template>
