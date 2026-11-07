<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    addressLines,
    addressMessages,
    type Address,
    type AddressMessages,
} from './address';

// A postal address for reading, in an <address> element: street, unit, "City, Region Postal" and the country, empty parts dropped, the region named in the
// locale's language. `hideCountry` drops the country line (an all-domestic list). `mapUrl` turns on a map link: a function from the address to a URL of the
// app's choosing, so the kit picks no map provider and makes no outside request. Strings as AddressFieldset's (`address.map`).
const props = withDefaults(
    defineProps<{
        address: Address | null | undefined;
        locale?: string;
        hideCountry?: boolean;
        mapUrl?: ((address: Address) => string | null) | null;
        messages?: Partial<AddressMessages>;
    }>(),
    {
        locale: undefined,
        hideCountry: false,
        mapUrl: null,
        messages: () => ({}),
    },
);
const page = usePage();
const locale = computed(
    () =>
        props.locale ??
        (page.props as { kitNumber?: { locale?: string } }).kitNumber?.locale ??
        (typeof navigator === 'undefined' ? 'en-CA' : navigator.language),
);
const lines = computed(() =>
    props.address
        ? addressLines(props.address, locale.value, !props.hideCountry)
        : [],
);
const href = computed(() =>
    props.address && props.mapUrl ? props.mapUrl(props.address) : null,
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
</script>
<template>
    <address v-if="lines.length" class="not-italic" data-address-text>
        <template v-for="(line, index) in lines" :key="index"
            >{{ line }}<br v-if="index < lines.length - 1"
        /></template>
        <a
            v-if="href"
            :href="href"
            class="text-primary mt-1 block text-sm underline-offset-4 hover:underline"
            data-address-map
            >{{ text.map }}</a
        >
    </address>
</template>
