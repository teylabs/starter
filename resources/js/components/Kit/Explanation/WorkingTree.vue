<script setup lang="ts">
import { computed } from 'vue';
import type { ExplanationNode } from './explanation.generated';
import { ledgerRows, humanNote } from './working';
const props = defineProps<{ node: ExplanationNode }>();
const rows = computed(() => ledgerRows(props.node));
</script>
<template>
    <div class="grid min-w-0 gap-3 text-sm" data-human-working>
        <dl class="grid min-w-0 gap-3">
            <div v-for="row in rows" :key="row.id" class="grid min-w-0 gap-1 border-b border-border pb-3 last:border-b-0 last:pb-0" data-working-row>
                <div class="flex min-w-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1" :class="row.children.length ? 'font-semibold' : ''">
                    <dt class="min-w-0 flex-1 wrap-anywhere">{{ row.label }}</dt>
                    <dd class="min-w-0 max-w-full wrap-anywhere tabular-nums">{{ row.value.display }}</dd>
                </div>
                <p v-if="humanNote(row)" class="min-w-0 wrap-anywhere text-muted-foreground">{{ humanNote(row) }}</p>
                <p v-for="excluded in row.exclusions" :key="excluded.key" class="wrap-anywhere text-muted-foreground">Excluded: {{ excluded.label }} — {{ excluded.reason }}</p>
            </div>
        </dl>
    </div>
</template>
