<script setup lang="ts">
import { computed } from 'vue';
import Input from './Input.vue';
import InputSelect from './InputSelect.vue';
import { regionOptions } from './address';

// A province, territory or state, on the kit's InputSelect (searchable: the US list is long), for Canada and the US; any other country takes the region as free
// text with `autocomplete="address-level1"`. The value is the ISO 3166-2 code without the country prefix ("ON", "NY"), named in French for a French `locale`.
// A current value that isn't in the list is kept as an option, so an old record still shows what it holds.
const props = withDefaults(
    defineProps<{
        country?: string | null;
        locale?: string;
        id?: string;
        name?: string;
        disabled?: boolean;
        invalid?: boolean;
        placeholder?: string;
    }>(),
    {
        country: null,
        locale: 'en-CA',
        id: undefined,
        name: undefined,
        placeholder: undefined,
    },
);
const model = defineModel<string | null>();
const options = computed(() => {
    const list = regionOptions(props.country, props.locale);
    if (!list) return null;
    return model.value && !list.some((option) => option.value === model.value)
        ? [{ value: model.value, label: model.value }, ...list]
        : list;
});
</script>
<template>
    <InputSelect
        v-if="options"
        :id="id"
        :name="name"
        :model-value="model ?? null"
        :options="options"
        :disabled="disabled"
        :invalid="invalid"
        :placeholder="placeholder"
        searchable
        chosen-first
        data-region-select
        @update:model-value="
            (value) =>
                (model =
                    value === null || value === undefined
                        ? null
                        : String(value))
        "
    />
    <Input
        v-else
        :id="id"
        :name="name"
        :model-value="model ?? ''"
        :disabled="disabled"
        :invalid="invalid"
        autocomplete="address-level1"
        data-region-select
        @update:model-value="(value) => (model = String(value ?? ''))"
    />
</template>
