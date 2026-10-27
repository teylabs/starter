<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';
import { flatten, move, type SearchGroup, type SearchOption } from './search';

/*
 * A search field with a live list of results (an ARIA combobox): typing waits 250ms, then asks `url` for JSON (`{ groups, partial }`, each provider's
 * first few results). Arrow keys move through every result across groups; Enter opens the active one, or with none active goes to the full results
 * page (`url?q=`). Escape clears, then closes. A stale answer (the person typed on, or left) is dropped. It renders the server's results as they are:
 * labels and descriptions are text, never markup. The host focuses it with the exposed `focus()`.
 */
const props = withDefaults(defineProps<{ url: string; label?: string; placeholder?: string; minLength?: number }>(), { label: 'Search', placeholder: 'Search', minLength: 1 });
const emit = defineEmits<{ navigate: [] }>();
const id = useId();
const input = ref<HTMLInputElement>();
const query = ref('');
const groups = ref<SearchGroup[]>([]);
const partial = ref(false);
const busy = ref(false);
const failed = ref(false);
const open = ref(false);
const active = ref(-1);
const options = computed<SearchOption[]>(() => flatten(groups.value));
const term = computed(() => query.value.trim());
const allResults = computed(() => `${props.url}?${new URLSearchParams({ q: term.value })}`);
let sequence = 0;
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

function reset(): void {
    sequence++;
    clearTimeout(timer);
    controller?.abort();
    groups.value = [];
    partial.value = false;
    busy.value = false;
    failed.value = false;
    active.value = -1;
}
watch(query, () => {
    reset();
    open.value = term.value.length >= props.minLength;
    if (!open.value) return;
    const asked = ++sequence;
    busy.value = true;
    timer = setTimeout(async () => {
        controller = new AbortController();
        try {
            const response = await fetch(`${props.url}?${new URLSearchParams({ q: term.value })}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: controller.signal });
            if (!response.ok) throw new Error(String(response.status));
            const body = (await response.json()) as { groups: SearchGroup[]; partial: boolean };
            if (asked !== sequence) return;
            groups.value = body.groups;
            partial.value = body.partial;
        } catch (error) {
            if (asked === sequence && !(error instanceof DOMException && error.name === 'AbortError')) failed.value = true;
        } finally {
            if (asked === sequence) busy.value = false;
        }
    }, 250);
});
onBeforeUnmount(reset);

function go(url: string): void {
    reset();
    open.value = false;
    query.value = '';
    emit('navigate');
    router.visit(url);
}
function keydown(event: KeyboardEvent): void {
    if (event.isComposing) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = term.value.length >= props.minLength;
        active.value = move(active.value, options.value.length, event.key === 'ArrowDown' ? 1 : -1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const chosen = options.value[active.value];
        if (chosen?.url) go(chosen.url);
        else if (term.value) go(allResults.value);
    } else if (event.key === 'Escape') {
        if (query.value) {
            event.preventDefault();
            query.value = '';
        } else open.value = false;
    }
}
const status = computed(() => (busy.value || !open.value ? '' : failed.value ? 'Search is unavailable. Try again.' : options.value.length === 0 ? 'No results' : `${options.value.length} results${partial.value ? ', some sources could not be searched' : ''}`));
defineExpose({ focus: () => input.value?.focus() });
</script>
<template>
    <div class="relative min-w-0" data-search-box>
        <input
            ref="input"
            v-model="query"
            type="search"
            role="combobox"
            autocomplete="off"
            :aria-label="label"
            :placeholder="placeholder"
            :aria-expanded="open"
            :aria-controls="`${id}-list`"
            :aria-activedescendant="options[active]?.optionId"
            aria-autocomplete="list"
            maxlength="100"
            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            data-search-input
            @keydown="keydown"
            @blur="open = false"
            @focus="open = term.length >= minLength"
        />
        <div v-show="open" :id="`${id}-list`" role="listbox" :aria-label="label" class="absolute inset-x-0 top-full z-50 mt-1 max-h-[60svh] min-w-72 overflow-y-auto rounded-md border border-border bg-popover p-1 text-sm text-popover-foreground shadow-lg" data-search-results @mousedown.prevent>
            <p v-if="busy" class="px-2 py-1.5 text-muted-foreground">Searching…</p>
            <p v-else-if="failed" class="px-2 py-1.5 text-destructive">Search is unavailable. Try again.</p>
            <p v-else-if="!options.length" class="px-2 py-1.5 text-muted-foreground" data-search-empty>No results</p>
            <template v-else>
                <div v-for="group in groups" :key="group.key" role="group" :aria-label="group.label" :data-search-group="group.key">
                    <p aria-hidden="true" class="px-2 pt-2 pb-1 text-xs font-medium text-muted-foreground">{{ group.label }}</p>
                    <div
                        v-for="option in options.filter((candidate) => candidate.group === group.key)"
                        :id="option.optionId"
                        :key="option.optionId"
                        role="option"
                        :aria-selected="options[active]?.optionId === option.optionId"
                        :aria-disabled="option.url ? undefined : 'true'"
                        :class="['grid gap-0.5 rounded-sm px-2 py-1.5', options[active]?.optionId === option.optionId && 'bg-accent text-accent-foreground', option.url ? 'cursor-pointer' : 'cursor-default']"
                        data-search-option
                        @click="option.url && go(option.url)"
                    >
                        <span class="font-medium">{{ option.label }}</span>
                        <span v-if="option.description || option.meta" class="text-xs text-muted-foreground">{{ [option.description, option.meta].filter(Boolean).join(' · ') }}</span>
                    </div>
                </div>
                <p v-if="partial" class="px-2 py-1.5 text-xs text-muted-foreground">Some sources could not be searched.</p>
            </template>
            <a v-if="term" :href="allResults" class="mt-1 block rounded-sm border-t border-border px-2 py-1.5 text-muted-foreground hover:bg-accent" data-search-all @click.prevent="go(allResults)">See all results</a>
        </div>
        <span class="sr-only" role="status" aria-live="polite">{{ status }}</span>
    </div>
</template>
