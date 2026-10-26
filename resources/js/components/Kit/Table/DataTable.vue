<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { type ColumnDef, getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Button from '@/components/Kit/Form/Button.vue';
import Input from '@/components/Kit/Form/Input.vue';
import RowActions from './RowActions.vue';

/*
 * A server-driven index table. The server owns the query (search, filters, sort, pages and tabs, in the URL) and decides each row's actions; TanStack Table runs
 * headless for the columns, their visibility and the rows, and everything is Tailwind utilities on the markup. Every state change is an Inertia visit that keeps
 * the old rows, dimmed (`aria-busy`), until the new ones arrive; back, forward and reload restore the view from the URL. Below `sm` each row is a stacked card.
 * Slots: `cell-<key>` ({ row, value }), `row-actions` ({ row }), `toolbar`, `empty-first-use`, `empty-no-results`. `intercept` names row actions the page performs itself
 * (it listens to `act`), such as one that opens a dialog. Nothing here checks a permission: an action that is not in the row is not shown.
 */
type Action = { key: string | null; label: string; url: string; method: string; destructive: boolean; confirm: { title: string; description: string; label: string } | null };
type Row = Record<string, any> & { id: string | number; actions?: Action[] };
type Column = { key: string; label: string; sortable: boolean; hideable: boolean; hiddenByDefault: boolean; align: string | null; srOnlyLabel: boolean; sortBy: string | null };
type Table = {
    rows: Row[];
    columns: Column[];
    sort: { column: string; direction: string } | null;
    search: string | null;
    filters: Record<string, string>;
    tabs: { key: string; label: string; url: string; count: number; current: boolean }[];
    pagination: { page: number; perPage: number; total: number; from: number | null; to: number | null; lastPage: number };
    emptyState: 'first-use' | 'no-results' | null;
};
const props = withDefaults(defineProps<{ table: Table; itemKey?: string; stickyHeader?: boolean; searchLabel?: string; intercept?: string[]; toolbar?: boolean }>(), { itemKey: 'id', stickyHeader: true, searchLabel: 'Search', intercept: () => [], toolbar: true });
const emit = defineEmits<{ act: [row: Row, action: Action] }>();

// ---- visits: the URL is the state ------------------------------------------------------------------------------------------------------------------
const busy = ref(false);
const stops = [router.on('start', () => (busy.value = true)), router.on('finish', () => (busy.value = false))];
onBeforeUnmount(() => stops.forEach((stop) => stop()));
const current = () => new URLSearchParams(window.location.search);
const tabKey = computed(() => props.table.tabs.find((tab) => tab.current)?.key ?? null);
function visit(patch: { search?: string | null; filters?: Record<string, string | null>; sort?: string | null; page?: number }, replace = false) {
    const query: Record<string, unknown> = {};
    const filter: Record<string, string> = { ...props.table.filters };
    if (patch.search !== undefined) patch.search ? (filter.search = patch.search) : delete filter.search;
    for (const [name, value] of Object.entries(patch.filters ?? {})) value ? (filter[name] = value) : delete filter[name];
    if (Object.keys(filter).length) query.filter = filter;
    const sort = patch.sort !== undefined ? patch.sort : current().get('sort');
    if (sort) query.sort = sort;
    if (tabKey.value && tabKey.value !== props.table.tabs[0]?.key) query.tab = tabKey.value;
    if (current().get('per_page')) query.per_page = current().get('per_page');
    if (patch.page && patch.page > 1) query.page = patch.page;
    router.get(window.location.pathname, query as never, { preserveState: true, preserveScroll: true, replace });
}
const search = ref(props.table.search ?? '');
watch(() => props.table.search, (value) => (search.value = value ?? ''));
let timer: ReturnType<typeof setTimeout> | undefined;
function typed() {
    clearTimeout(timer);
    timer = setTimeout(() => visit({ search: search.value.trim() || null }, true), 300);
}
function submitSearch() {
    clearTimeout(timer);
    visit({ search: search.value.trim() || null }, true);
}
onBeforeUnmount(() => clearTimeout(timer));
const clear = () => visit({ search: null, filters: Object.fromEntries(Object.keys(props.table.filters).map((name) => [name, null])) });
const ariaSort = (column: Column) => (!column.sortable || props.table.sort?.column !== column.key ? 'none' : props.table.sort.direction === 'desc' ? 'descending' : 'ascending');
function sortBy(column: Column) {
    const active = props.table.sort?.column === column.key;
    // The `?sort=` value is the column's own `sortBy` (what the server allows), not necessarily its key.
    const param = column.sortBy ?? column.key;
    visit({ sort: !active ? param : props.table.sort?.direction === 'asc' ? `-${param}` : null });
}

// ---- columns: TanStack, headless ----------------------------------------------------------------------------------------------------------------------
const storageKey = computed(() => `kit.table.${window.location.pathname}.${tabKey.value ?? ''}`);
const shown = ref<Record<string, boolean>>({});
watch(storageKey, () => {
    const defaults = Object.fromEntries(props.table.columns.filter((column) => column.hideable && column.hiddenByDefault).map((column) => [column.key, false]));
    try {
        shown.value = { ...defaults, ...JSON.parse(localStorage.getItem(storageKey.value) ?? '{}') };
    } catch {
        shown.value = defaults;
    }
}, { immediate: true });
function toggle(column: Column, visible: boolean) {
    shown.value = { ...shown.value, [column.key]: visible };
    try {
        localStorage.setItem(storageKey.value, JSON.stringify(shown.value));
    } catch {
        // a per-viewer convenience: without storage the server's defaults apply
    }
}
const definitions = computed<ColumnDef<Row>[]>(() => props.table.columns.map((column) => ({ id: column.key, accessorFn: (row) => row[column.key], header: column.label, meta: column })));
const visibility = computed(() => Object.fromEntries(props.table.columns.map((column) => [column.key, column.hideable ? shown.value[column.key] !== false : true])));
const tanstack = useVueTable({
    get data() { return props.table.rows; },
    get columns() { return definitions.value; },
    state: { get columnVisibility() { return visibility.value; } },
    getRowId: (row) => String(row[props.itemKey]),
    getCoreRowModel: getCoreRowModel(),
    manualSorting: true,
    manualPagination: true,
    manualFiltering: true,
});
const meta = (column: unknown) => (column as { columnDef: { meta: Column } }).columnDef.meta;
const hideable = computed(() => props.table.columns.filter((column) => column.hideable));
const align = (column: Column) => (column.align === 'end' ? 'text-right' : 'text-left');
const text = (value: unknown) => (value === null || value === undefined ? '' : String(value));
const pages = computed(() => {
    const { page, lastPage } = props.table.pagination;
    return Array.from({ length: lastPage }, (_, index) => index + 1).filter((number) => number === 1 || number === lastPage || Math.abs(number - page) <= 1);
});
const pageUrl = (page: number) => {
    const query = new URLSearchParams(window.location.search);
    page > 1 ? query.set('page', String(page)) : query.delete('page');
    return `${window.location.pathname}${query.size ? `?${query}` : ''}`;
};
const showToolbar = computed(() => props.toolbar && props.table.emptyState !== 'first-use');
</script>
<template>
    <section class="grid gap-4" data-data-table>
        <nav v-if="table.tabs.length" aria-label="Views" class="flex gap-1 border-b border-border" data-table-tabs>
            <Link v-for="tab in table.tabs" :key="tab.key" :href="tab.url" :aria-current="tab.current ? 'page' : undefined" preserve-scroll class="-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium" :class="tab.current ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'" :data-tab="tab.key">
                {{ tab.label }}<span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground" data-tab-count>{{ tab.count }}</span>
            </Link>
        </nav>
        <div v-if="showToolbar" class="flex flex-wrap items-center gap-2" data-table-toolbar>
            <form class="flex min-w-0 flex-1 items-center gap-2" role="search" @submit.prevent="submitSearch">
                <Input v-model="search" type="search" :placeholder="`${searchLabel}…`" :aria-label="searchLabel" class="max-w-xs" data-table-search @input="typed" />
            </form>
            <slot name="toolbar" />
            <DropdownMenu v-if="hideable.length">
                <DropdownMenuTrigger as-child><Button variant="secondary" type="button" data-columns-menu>Columns</Button></DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuCheckboxItem v-for="column in hideable" :key="column.key" :model-value="shown[column.key] !== false" @select.prevent @update:model-value="toggle(column, Boolean($event))">{{ column.label }}</DropdownMenuCheckboxItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
        <div v-if="table.emptyState === 'first-use'" class="grid justify-items-start gap-2 rounded-lg p-6 ring-1 ring-border" data-empty="first-use"><slot name="empty-first-use"><p class="text-sm text-muted-foreground">Nothing here yet.</p></slot></div>
        <div v-else-if="table.emptyState === 'no-results'" class="grid justify-items-start gap-2 rounded-lg p-6 ring-1 ring-border" data-empty="no-results">
            <slot name="empty-no-results"><p class="font-medium">No results<template v-if="table.search"> for “{{ table.search }}”</template></p></slot>
            <Button variant="secondary" type="button" data-clear-search @click="clear">Clear search</Button>
        </div>
        <div v-else class="overflow-hidden rounded-lg ring-1 ring-border" :class="busy ? 'opacity-60' : ''" data-table-card>
            <div class="overflow-x-auto" :class="stickyHeader ? 'max-h-[70svh] overflow-y-auto' : ''">
                <table class="w-full border-collapse text-sm max-sm:block" :aria-busy="busy" data-table>
                    <thead class="bg-muted/60 text-xs text-muted-foreground max-sm:sr-only" :class="stickyHeader ? 'sticky top-0 z-10' : ''">
                        <tr v-for="group in tanstack.getHeaderGroups()" :key="group.id">
                            <th v-for="header in group.headers" :key="header.id" scope="col" :aria-sort="ariaSort(meta(header.column))" class="px-4 py-2 font-medium first:sticky first:left-0 first:bg-muted" :class="align(meta(header.column))" :data-column="header.id">
                                <span v-if="meta(header.column).srOnlyLabel" class="sr-only">{{ meta(header.column).label }}</span>
                                <Button v-else-if="meta(header.column).sortable" variant="secondary" type="button" class="-mx-2 h-7 border-0 bg-transparent px-2 text-xs shadow-none" :data-sort="header.id" @click="sortBy(meta(header.column))">
                                    {{ meta(header.column).label }}<span aria-hidden="true">{{ ariaSort(meta(header.column)) === 'ascending' ? ' ↑' : ariaSort(meta(header.column)) === 'descending' ? ' ↓' : '' }}</span>
                                </Button>
                                <template v-else>{{ meta(header.column).label }}</template>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="max-sm:grid max-sm:gap-3 max-sm:p-3">
                        <tr v-for="row in tanstack.getRowModel().rows" :key="row.id" class="border-b border-border last:border-b-0 max-sm:grid max-sm:gap-1 max-sm:rounded-lg max-sm:border max-sm:p-3 max-sm:last:border-b" data-row>
                            <td v-for="(cell, index) in row.getVisibleCells()" :key="cell.id" class="px-4 py-3 align-middle first:sticky first:left-0 first:bg-background max-sm:flex max-sm:items-center max-sm:justify-between max-sm:gap-3 max-sm:px-0 max-sm:py-1 max-sm:first:static" :class="align(meta(cell.column))" :data-cell="cell.column.id">
                                <!-- The phone card's field label: shown for every visible column after the first (the card's title) unless the column hides its label (actions). -->
                                <span class="hidden text-xs text-muted-foreground" :class="index > 0 && !meta(cell.column).srOnlyLabel ? 'max-sm:inline' : ''" data-cell-label>{{ meta(cell.column).label }}</span>
                                <template v-if="meta(cell.column).srOnlyLabel">
                                    <slot name="row-actions" :row="row.original"><RowActions :actions="row.original.actions ?? []" :label="`Actions for row ${row.index + 1}`" :intercept="intercept" @act="(action) => emit('act', row.original, action)" /></slot>
                                </template>
                                <slot v-else :name="`cell-${cell.column.id}`" :row="row.original" :value="cell.getValue()">{{ text(cell.getValue()) }}</slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="table.pagination.total > 0" class="flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-3 text-sm text-muted-foreground" data-table-pagination>
                <span>{{ table.pagination.from }}–{{ table.pagination.to }} of {{ table.pagination.total }}</span>
                <nav v-if="table.pagination.lastPage > 1" aria-label="Pages" class="flex items-center gap-1">
                    <Link v-if="table.pagination.page > 1" :href="pageUrl(table.pagination.page - 1)" preserve-scroll preserve-state class="rounded-md px-2 py-1 hover:bg-muted" rel="prev">Previous</Link>
                    <Link v-for="number in pages" :key="number" :href="pageUrl(number)" preserve-scroll preserve-state class="hidden rounded-md px-2 py-1 hover:bg-muted sm:inline" :class="number === table.pagination.page ? 'font-semibold text-foreground' : ''" :aria-current="number === table.pagination.page ? 'page' : undefined">{{ number }}</Link>
                    <Link v-if="table.pagination.page < table.pagination.lastPage" :href="pageUrl(table.pagination.page + 1)" preserve-scroll preserve-state class="rounded-md px-2 py-1 hover:bg-muted" rel="next">Next</Link>
                </nav>
            </div>
        </div>
    </section>
</template>
