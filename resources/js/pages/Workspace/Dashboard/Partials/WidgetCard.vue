<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Button } from '@kit/ui';
import type { WidgetFrame } from './types';

// The card every widget renders in: its title and header actions, then its body, its empty state or its error with Retry. The body is the default slot.
const props = defineProps<{ widget: WidgetFrame }>();
const retry = () => router.reload();
</script>
<template>
    <section :aria-labelledby="`widget-${props.widget.key}`" class="flex min-w-0 flex-col rounded-xl border bg-card text-card-foreground shadow-sm" :data-widget="widget.key" :data-state="widget.state">
        <header class="flex min-h-12 items-center justify-between gap-3 border-b px-4 py-2">
            <h2 :id="`widget-${widget.key}`" class="truncate text-sm font-semibold">{{ widget.title }}</h2>
            <div v-if="widget.actions.length && widget.state !== 'error'" class="flex shrink-0 items-center gap-2">
                <Link v-for="action in widget.actions" :key="action.key ?? action.url" :href="action.url" :method="action.method as 'get'" class="rounded-md px-2 py-1 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ action.label }}</Link>
            </div>
        </header>
        <div class="min-h-0 flex-1 p-4">
            <div v-if="widget.state === 'error'" role="alert" class="grid justify-items-start gap-2" data-widget-error>
                <p class="text-sm text-muted-foreground">{{ widget.error?.message ?? "This couldn't load." }}</p>
                <Button type="button" variant="secondary" @click="retry">{{ widget.error?.retry?.label ?? 'Retry' }}</Button>
            </div>
            <div v-else-if="widget.state === 'empty' && widget.empty" class="grid justify-items-start gap-1" data-widget-empty>
                <p class="font-medium">{{ widget.empty.title }}</p>
                <p class="text-sm text-muted-foreground">{{ widget.empty.description }}</p>
                <Link v-if="widget.empty.action" :href="widget.empty.action.url" class="mt-2 inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ widget.empty.action.label }}</Link>
            </div>
            <slot v-else />
        </div>
    </section>
</template>
