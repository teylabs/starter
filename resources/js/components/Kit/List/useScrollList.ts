import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import type { InfiniteScrollRef } from '@inertiajs/core';
export type ListAction = { key: string | null; label: string; url: string; method: string; destructive: boolean; confirm: { title: string; description: string; label: string } | null; disabled?: boolean; reason?: string | null };
export type ListItem = { id: string; title: string; url: string | null; description: string | null; meta: { icon: string | null; label: string; url: string | null }[]; badges: { label: string; variant: string }[]; variant: string | null; group: { key: string; label: string } | null; actions: ListAction[] };
export type ScrollPayload<T extends ListItem = ListItem> = { key: string; title: string; state: 'ready' | 'empty' | 'error'; data: T[]; empty: { variant: string; title: string; description: string; action: ListAction | null } | null; error: { message: string; retry: ListAction | null } | null };

// One controller owns resets and appends. Unmount before reset cancels the native pending append.
export function useScrollList<T extends ListItem = ListItem>(key: string) {
    const page = usePage();
    const list = computed(() => page.props[key] as ScrollPayload<T> | undefined);
    const items = computed(() => list.value?.data ?? []);
    const loadedCount = computed(() => items.value.length);
    const groups = computed(() => {
        const result: { group: ListItem['group']; items: T[] }[] = [];
        for (const item of items.value) {
            const previous = result.at(-1);
            if (!previous || previous.group?.key !== item.group?.key) result.push({ group: item.group, items: [item] });
            else previous.items.push(item);
        }
        return result;
    });
    const stream = ref<InfiniteScrollRef>();
    const region = ref<HTMLElement>();
    const mounted = ref(true);
    const generation = ref(0);
    const loading = ref(false);
    const error = ref(false);
    const announcement = ref('');
    let previous = items.value.length;
    let initialized = Boolean(list.value);
    let buttonHadFocus = false;
    const hasMore = computed(() => Boolean(page.scrollProps?.[key]?.nextPage));
    watch(() => items.value.length, async (count) => {
        if (initialized && count > previous) {
            announcement.value = `Loaded ${count - previous} more. Showing ${count}.` + (!hasMore.value ? ' All items shown.' : '');
            if (buttonHadFocus && !hasMore.value) {
                await nextTick();
                region.value?.querySelectorAll<HTMLElement>('[data-list-item]')[previous]?.focus();
            }
        }
        initialized = true;
        previous = count;
        buttonHadFocus = false;
    });
    const request = {
        onStart: () => { buttonHadFocus ||= document.activeElement?.hasAttribute('data-load-more') ?? false; loading.value = true; error.value = false; },
        onSuccess: () => { if (list.value?.state === 'error') error.value = true; },
        onError: () => { error.value = true; },
        onHttpException: () => { error.value = true; return false; },
        onNetworkError: () => { error.value = true; return false; },
        onFinish: () => { loading.value = false; },
    };
    function fetchNext() {
        if (loading.value) return;
        buttonHadFocus = document.activeElement?.hasAttribute('data-load-more') ?? false;
        stream.value?.fetchNext(request);
    }
    async function refresh() {
        mounted.value = false;
        await nextTick();
        router.reload({ only: [key], reset: [key], data: { [`${key}_cursor`]: null }, ...request,
            onSuccess: async () => {
                initialized = false;
                generation.value++;
                mounted.value = true;
                await nextTick();
                region.value?.scrollTo({ top: 0 });
                region.value?.focus();
            },
            onFinish: () => { loading.value = false; mounted.value = true; },
        });
    }
    function refreshEvent(event: Event) {
        if ((event as CustomEvent).detail === key) { event.preventDefault(); void refresh(); }
    }
    onMounted(() => window.addEventListener('scroll-list:refresh', refreshEvent));
    onUnmounted(() => window.removeEventListener('scroll-list:refresh', refreshEvent));
    return { list, items, groups, loadedCount, stream, region, mounted, generation, hasMore, loading, error, announcement, fetchNext, refresh, request };
}
