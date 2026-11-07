<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useOptionalFormContext } from '@/components/Kit/Form/formContext';
import Field from './Field.vue';
import FormFieldset from './FormFieldset.vue';
import Input from './Input.vue';
import InputSelect from './InputSelect.vue';
import RegionSelect from './RegionSelect.vue';
import {
    addressAutocomplete,
    addressLabels,
    addressMessages,
    countryName,
    normalizePostal,
    postalLooksValid,
    type Address,
    type AddressMessages,
    type AddressPart,
} from './address';

// A postal address as one named group: FormFieldset (a fieldset whose legend is `title`) holding street, unit, city, region, postal code and country. The fields
// submit as `<name>[line1]`, `<name>[line2]`, `<name>[city]`, `<name>[region]`, `<name>[postal_code]` and `<name>[country]`, and read their errors from the form
// as `<name>.<part>`. `countries` lists the choices (Canada and the US by default); a fixed `country` hides the country field. The region and postal-code labels
// follow the country (Province or territory and Postal code, State and ZIP code, Region and Postal code), and only their text changes, with no announcement.
// The text fields carry their autocomplete tokens. A Canadian postal code is written A1A 1A1 on blur; a code that doesn't look right for Canada or the US shows a
// hint under the field, never a block: the server validates. Strings come from the kit's `ui` lang file (`address.*`, shared as `kitUi`); `messages` overrides them.
const props = withDefaults(
    defineProps<{
        name?: string;
        title?: string;
        description?: string;
        level?: 2 | 3 | 4 | 5 | 6;
        countries?: string[];
        country?: string | null;
        locale?: string;
        required?: boolean;
        disabled?: boolean;
        messages?: Partial<AddressMessages>;
    }>(),
    {
        name: 'address',
        title: undefined,
        description: undefined,
        level: 2,
        countries: () => ['CA', 'US'],
        country: null,
        locale: undefined,
        required: false,
        disabled: false,
        messages: () => ({}),
    },
);
const model = defineModel<Address>({ default: () => ({}) });
const page = usePage();
const locale = computed(
    () =>
        props.locale ??
        (page.props as { kitNumber?: { locale?: string } }).kitNumber?.locale ??
        (typeof navigator === 'undefined' ? 'en-CA' : navigator.language),
);
const text = computed(() => {
    let shared: unknown;
    try {
        shared = (page.props as { kitUi?: { address?: unknown } }).kitUi
            ?.address;
    } catch {
        shared = undefined;
    }
    return addressMessages(shared, props.messages);
});
const country = computed(
    () => props.country ?? model.value.country ?? props.countries[0] ?? null,
);
const labels = computed(() => addressLabels(country.value, text.value));
const countryOptions = computed(() =>
    props.countries.map((code) => ({
        value: code,
        label: countryName(code, locale.value),
    })),
);
const form = useOptionalFormContext() as
    { errors?: Record<string, string> } | undefined;
const errorOf = (part: AddressPart) => form?.errors?.[`${props.name}.${part}`];
const fieldName = (part: AddressPart) => `${props.name}[${part}]`;
const id = (part: AddressPart) => `${props.name}-${part.replace('_', '-')}`;
const set = (part: AddressPart, value: string | null) => {
    model.value = {
        ...model.value,
        [part]: value,
        ...(part === 'country' && value !== model.value.country
            ? { region: null }
            : {}),
    };
};
const postalHint = ref(false);
// Each control is described by its hint (postal code only) and its error, the ids Field gives them.
const describedBy = (part: AddressPart, hint = false) =>
    [hint && `${id(part)}-description`, errorOf(part) && `${id(part)}-error`]
        .filter(Boolean)
        .join(' ') || undefined;
function postalBlur() {
    const value = normalizePostal(country.value, model.value.postal_code ?? '');
    if (value !== (model.value.postal_code ?? '')) set('postal_code', value);
    postalHint.value = !postalLooksValid(country.value, value);
}
if (!props.country && !model.value.country && props.countries[0])
    model.value = { ...model.value, country: props.countries[0] };
</script>
<template>
    <FormFieldset
        :title="title"
        :description="description"
        :level="level"
        data-address-fieldset
    >
        <Field
            :id="id('line1')"
            :label="text.line1"
            :error="errorOf('line1')"
            :span="6"
        >
            <Input
                :id="id('line1')"
                :name="fieldName('line1')"
                :model-value="model.line1 ?? ''"
                :autocomplete="addressAutocomplete.line1"
                :required="required"
                :disabled="disabled"
                :invalid="Boolean(errorOf('line1'))"
                :aria-describedby="describedBy('line1')"
                @update:model-value="(v) => set('line1', String(v))"
            />
        </Field>
        <Field
            :id="id('line2')"
            :label="text.line2"
            :error="errorOf('line2')"
            :span="6"
        >
            <Input
                :id="id('line2')"
                :name="fieldName('line2')"
                :model-value="model.line2 ?? ''"
                :autocomplete="addressAutocomplete.line2"
                :disabled="disabled"
                :invalid="Boolean(errorOf('line2'))"
                :aria-describedby="describedBy('line2')"
                @update:model-value="(v) => set('line2', String(v))"
            />
        </Field>
        <Field
            :id="id('city')"
            :label="text.city"
            :error="errorOf('city')"
            :span="3"
        >
            <Input
                :id="id('city')"
                :name="fieldName('city')"
                :model-value="model.city ?? ''"
                :autocomplete="addressAutocomplete.city"
                :required="required"
                :disabled="disabled"
                :invalid="Boolean(errorOf('city'))"
                :aria-describedby="describedBy('city')"
                @update:model-value="(v) => set('city', String(v))"
            />
        </Field>
        <Field
            :id="id('region')"
            :label="labels.region"
            :error="errorOf('region')"
            :span="3"
        >
            <RegionSelect
                :id="id('region')"
                :name="fieldName('region')"
                :model-value="model.region ?? null"
                :country="country"
                :locale="locale"
                :placeholder="text.region_placeholder"
                :disabled="disabled"
                :invalid="Boolean(errorOf('region'))"
                :aria-describedby="describedBy('region')"
                @update:model-value="(v) => set('region', v ?? null)"
            />
        </Field>
        <Field
            :id="id('postal_code')"
            :label="labels.postal_code"
            :error="errorOf('postal_code')"
            :description="
                postalHint && labels.postal_hint
                    ? labels.postal_hint
                    : undefined
            "
            :span="3"
        >
            <Input
                :id="id('postal_code')"
                :name="fieldName('postal_code')"
                :model-value="model.postal_code ?? ''"
                :autocomplete="addressAutocomplete.postal_code"
                :required="required"
                :disabled="disabled"
                :invalid="Boolean(errorOf('postal_code'))"
                :aria-describedby="
                    describedBy(
                        'postal_code',
                        postalHint && Boolean(labels.postal_hint),
                    )
                "
                @update:model-value="
                    (v) => {
                        set('postal_code', String(v));
                        postalHint = false;
                    }
                "
                @blur="postalBlur"
            />
        </Field>
        <Field
            v-if="!props.country"
            :id="id('country')"
            :label="text.country"
            :error="errorOf('country')"
            :span="3"
        >
            <InputSelect
                :id="id('country')"
                :name="fieldName('country')"
                :model-value="model.country ?? null"
                :options="countryOptions"
                :placeholder="text.country_placeholder"
                :searchable="countries.length > 7"
                :clearable="false"
                :disabled="disabled"
                :invalid="Boolean(errorOf('country'))"
                :aria-describedby="describedBy('country')"
                @update:model-value="
                    (v) =>
                        set(
                            'country',
                            v === null || v === undefined ? null : String(v),
                        )
                "
            />
        </Field>
        <input
            v-else
            type="hidden"
            :name="fieldName('country')"
            :value="props.country"
        />
    </FormFieldset>
</template>
