<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import RowActions from '@/components/Kit/Table/RowActions.vue';
import type { ListItem } from './useScrollList';

// The default row of a ScrollList: a title (a link when the row has a url), its badges, one muted line of description and meta, and the row's server-decided actions through
// RowActions (one or two inline, three or more in a menu, a destructive one behind a confirmation). A richer row replaces it through the list's `row` slot.
const props = defineProps<{ item: ListItem }>();
const line = computed(() => [props.item.description, ...props.item.meta.map((value) => value.label)].filter(Boolean).join(' · '));
</script>
<template>
    <div class="flex min-w-0 items-start justify-between gap-3">
        <div class="grid min-w-0 flex-1 gap-1">
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <h3 class="min-w-0 truncate text-sm font-medium"><Link v-if="item.url" :href="item.url" :title="item.title" class="rounded-sm underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{{ item.title }}</Link><template v-else>{{ item.title }}</template></h3>
                <span v-for="badge in item.badges" :key="badge.label" class="inline-flex items-center rounded-md border border-border px-1.5 py-0.5 text-xs font-medium text-muted-foreground" :data-variant="badge.variant">{{ badge.label }}</span>
            </div>
            <p v-if="line" class="min-w-0 truncate text-xs text-muted-foreground">{{ line }}</p>
        </div>
        <RowActions v-if="item.actions.length" :actions="item.actions" :label="item.title" class="shrink-0" />
    </div>
</template>
