<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, House } from '@lucide/vue';
import { computed } from 'vue';

// The trail to the page (the page's `layout.breadcrumbs`, gretel's trail mapped once on the server), always shown so the page does not shift: it is rooted at "/", which shows as a home icon
// named by the workspace (or the product, with one workspace). The Dashboard, Settings and the personal pages are its siblings.
type Crumb = Tey.Kit.Features.Core.Data.Breadcrumb;
const page = usePage<{ layout?: { breadcrumbs: Crumb[] } }>();
const breadcrumbs = computed(() => page.props.layout?.breadcrumbs ?? []);
</script>
<template>
    <nav aria-label="Breadcrumb" class="py-2" data-shell-breadcrumbs>
        <ol v-if="breadcrumbs.length" class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
            <li v-for="(crumb, index) in breadcrumbs" :key="index" class="flex items-center gap-1">
                <ChevronRight v-if="index > 0" class="size-3.5" aria-hidden="true" />
                <!-- Every crumb is a link, the current page's too (to its own URL): aria-current marks it, and it reads in the foreground colour. -->
                <Link v-if="index === 0" :href="crumb.url ?? '#'" :aria-label="crumb.label" :aria-current="crumb.current ? 'page' : undefined" :class="['inline-flex size-6 items-center justify-center rounded-md hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', crumb.current ? 'text-foreground' : '']" data-breadcrumb-home><House class="size-4" aria-hidden="true" /></Link>
                <Link v-else :href="crumb.url ?? '#'" :aria-current="crumb.current ? 'page' : undefined" :class="['rounded-sm hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', crumb.current ? 'text-foreground' : '']">{{ crumb.label }}</Link>
            </li>
        </ol>
    </nav>
</template>
