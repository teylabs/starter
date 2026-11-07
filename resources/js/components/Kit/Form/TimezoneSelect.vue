<script setup lang="ts">
import { computed } from 'vue';
import InputSelect from './InputSelect.vue';

// The IANA timezones the browser knows, on the kit's InputSelect (searchable: the list is long), with the current value kept even when it is not among them.
// The current zone leads the list, then the browser's own, then the rest A to Z; `chosen-first` keeps the chosen one on top each time the list opens.
const props = defineProps<{
    id?: string;
    name?: string;
    disabled?: boolean;
    invalid?: boolean;
    autofocus?: boolean;
}>();
const model = defineModel<string>();
function browserZone(): string | null {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
    } catch {
        return null;
    }
}
const zones = computed(() => {
    const known =
        typeof Intl.supportedValuesOf === 'function'
            ? Intl.supportedValuesOf('timeZone')
            : [];
    const leading = [model.value, browserZone()].filter(
        (zone): zone is string => !!zone,
    );
    const rest = [...new Set(['UTC', ...known])]
        .filter((zone) => !leading.includes(zone))
        .sort();

    return [...new Set([...leading, ...rest])].map((zone) => ({
        value: zone,
        label: zone.replace(/_/g, ' '),
    }));
});
</script>
<template>
    <InputSelect
        :id="id"
        :name="name"
        :model-value="model ?? null"
        :options="zones"
        :disabled="disabled"
        :invalid="invalid"
        :autofocus="props.autofocus || undefined"
        searchable
        :clearable="false"
        chosen-first
        placeholder="Choose a timezone"
        data-timezone-select
        @update:model-value="
            (value) =>
                (model =
                    value === null || value === undefined
                        ? undefined
                        : String(value))
        "
    />
</template>
