<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import Input from '@/components/Kit/Form/Input.vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';

/*
 * The index page's toolbar: the search box and one choice per filter the server declared with options (TableData `choices`). It only reports what changed; the
 * DataTable turns that into a visit, so the URL stays the state. Search waits 300ms after typing (Enter sends at once). A filter's options are the only values it
 * can send; the server refuses any other. The default slot sits after the filters (tabs, a segment control, page-specific controls); `end` sits at the far end.
 */
type Choice = { key: string; label: string; options: { value: string; label: string }[] };
const props = withDefaults(defineProps<{ search?: string | null; filters?: Record<string, string>; choices?: Choice[]; searchLabel?: string; searchable?: boolean }>(), { search: null, filters: () => ({}), choices: () => [], searchLabel: 'Search', searchable: true });
const emit = defineEmits<{ search: [value: string | null]; filter: [name: string, value: string | null] }>();
const text = ref(props.search ?? '');
watch(() => props.search, (value) => (text.value = value ?? ''));
let timer: ReturnType<typeof setTimeout> | undefined;
function typed() {
    clearTimeout(timer);
    timer = setTimeout(() => emit('search', text.value.trim() || null), 300);
}
function submit() {
    clearTimeout(timer);
    emit('search', text.value.trim() || null);
}
onBeforeUnmount(() => clearTimeout(timer));
</script>
<template>
    <div class="flex flex-wrap items-center gap-2" data-index-toolbar data-table-toolbar>
        <form v-if="searchable" class="flex min-w-0 flex-1 items-center gap-2" role="search" @submit.prevent="submit">
            <Input v-model="text" type="search" :placeholder="`${searchLabel}…`" :aria-label="searchLabel" class="max-w-xs" data-table-search @input="typed" />
        </form>
        <div v-for="choice in choices" :key="choice.key" class="w-40 max-sm:flex-1" :data-table-filter="choice.key">
            <InputSelect :model-value="filters[choice.key] ?? null" :options="choice.options" :placeholder="choice.label" :aria-label="choice.label" clearable :searchable="false" @update:model-value="(value) => emit('filter', choice.key, value === null || value === undefined || value === '' ? null : String(value))" />
        </div>
        <slot />
        <div class="ms-auto flex items-center gap-2"><slot name="end" /></div>
    </div>
</template>
