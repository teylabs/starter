<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Button, InputSelect, provideCard } from '@kit/ui';
import { computed, ref, useId, watch } from 'vue';
import type { WidgetFrame } from './types';

// The card every widget renders in: its title and header actions, then its body, its empty state or its error with Retry. The body is the default slot.
// It is a card on any ground, so a kit component inside (an EmptyState, a DataTable) draws no ground card of its own. `headless` drops the title bar (the You
// card): the card keeps the title as its accessible name, and its body renders the actions itself.
// A widget that declares orders (`sortBy`) gets a Sort by control at the header's right, before its actions: a labelled InputSelect (keyboard: Enter or the
// arrows open it, the arrows move, Enter picks, Escape closes). A choice is saved for the viewer (PUT, the server checks it against the widget) and reloads
// only the props the widget names (its own lists, else `widgets`); the body remounts on the new order, so a list starts again from its top.
// A widget's `loadingHeight` is the ready body's minimum height, so the card holds the space its deferred content will fill and nothing below shifts.
const props = defineProps<{ widget: WidgetFrame; headless?: boolean }>();
provideCard();
const loadingHeight = computed(() => (props.widget.state === 'ready' && props.widget.loadingHeight) || null);
const retry = () => router.reload();
const sortId = useId();
const sort = computed(() => props.widget.sortBy ?? null);
const sortOptions = computed(() => (sort.value?.options ?? []).map((option) => ({ value: option.key, label: option.label })));
const current = ref(sort.value?.current ?? null);
const shown = ref(current.value);
const saving = ref(false);
watch(() => sort.value?.current, (value) => { current.value = value ?? null; shown.value = value ?? null; });
const choose = (value: unknown) => {
    const next = typeof value === 'string' ? value : null;
    if (!sort.value || next === null || next === shown.value || saving.value) return;
    const previous = shown.value;
    current.value = next;
    saving.value = true;
    router.visit(sort.value.url, {
        method: sort.value.method as 'put',
        data: { sort: next },
        only: sort.value.reload,
        // A list starts again from its first page; `widgets` is a plain prop, so it needs no reset.
        reset: sort.value.reload.filter((key) => key !== 'widgets'),
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { shown.value = next; },
        onError: () => { current.value = previous; },
        onFinish: () => { saving.value = false; },
    });
};
</script>
<template>
    <section :aria-labelledby="headless ? undefined : `widget-${props.widget.key}`" :aria-label="headless ? widget.title : undefined" class="flex min-w-0 flex-col rounded-xl border bg-card text-card-foreground shadow-sm" :data-widget="widget.key" :data-state="widget.state">
        <header v-if="!headless" class="flex min-h-12 items-center justify-between gap-3 border-b px-4 py-2">
            <h2 :id="`widget-${widget.key}`" class="truncate text-sm font-semibold">{{ widget.title }}</h2>
            <div v-if="(sort && widget.state === 'ready') || (widget.actions.length && widget.state !== 'error')" class="flex shrink-0 items-center gap-2">
                <div v-if="sort && widget.state === 'ready'" class="flex items-center gap-2" data-widget-sort>
                    <label :for="sortId" class="text-xs font-medium whitespace-nowrap text-muted-foreground">{{ sort.label }}</label>
                    <div class="w-40">
                        <InputSelect :id="sortId" :model-value="current" :options="sortOptions" :searchable="false" :clearable="false" :disabled="saving" @update:model-value="choose" />
                    </div>
                </div>
                <Link v-for="action in widget.actions" :key="action.key ?? action.url" :href="action.url" :method="action.method as 'get'" class="rounded-md px-2 py-1 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ action.label }}</Link>
            </div>
        </header>
        <div :class="['flex-1 p-4', loadingHeight ? 'min-h-(--kit-widget-loading-height)' : 'min-h-0']" :style="loadingHeight ? { '--kit-widget-loading-height': loadingHeight } : undefined" :data-widget-loading-height="loadingHeight || undefined">
            <div v-if="widget.state === 'error'" role="alert" class="grid justify-items-start gap-2" data-widget-error>
                <p class="text-sm text-muted-foreground">{{ widget.error?.message ?? "This couldn't load." }}</p>
                <Button type="button" variant="secondary" @click="retry">{{ widget.error?.retry?.label ?? 'Retry' }}</Button>
            </div>
            <div v-else-if="widget.state === 'empty' && widget.empty" class="grid justify-items-start gap-1" data-widget-empty>
                <p class="font-medium">{{ widget.empty.title }}</p>
                <p class="text-sm text-muted-foreground">{{ widget.empty.description }}</p>
                <Link v-if="widget.empty.action" :href="widget.empty.action.url" class="mt-2 inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ widget.empty.action.label }}</Link>
            </div>
            <div v-else :key="shown ?? ''" class="contents"><slot /></div>
        </div>
    </section>
</template>
