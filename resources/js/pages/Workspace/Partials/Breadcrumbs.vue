<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';

// The trail to the page, from the server's registered pages. A crumb the user can't open has no link and reads as text.
defineProps<{ breadcrumbs: Tey.Kit.Features.SessionUi.Data.BreadcrumbData[] }>();
</script>
<template>
    <nav v-if="breadcrumbs.length > 1" aria-label="Breadcrumb" class="mb-3" data-shell-breadcrumbs>
        <ol class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
            <li v-for="(crumb, index) in breadcrumbs" :key="index" class="flex items-center gap-1">
                <ChevronRight v-if="index > 0" class="size-3.5" aria-hidden="true" />
                <Link v-if="crumb.url && !crumb.current" :href="crumb.url" class="hover:text-foreground hover:underline">{{ crumb.label }}</Link>
                <span v-else :aria-current="crumb.current ? 'page' : undefined" :class="crumb.current ? 'text-foreground' : ''">{{ crumb.label }}</span>
            </li>
        </ol>
    </nav>
</template>
