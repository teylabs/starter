<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { numberDefaults, numberFormat } from './number';

// The display half of InputNumber's money and percent: the same canonical string (money in integer minor units, percent in percentage points) shown with the
// locale's grouping, decimal and symbol placement, in tabular figures so columns line up. `negative="parentheses"` is the accounting form; the parentheses are
// visual, so a screen reader hears "negative" instead. Locale and currency resolve as InputNumber's do; money without a currency shows no symbol. Empty renders nothing.
const props = withDefaults(
    defineProps<{
        value: string | null | undefined;
        kind: 'money' | 'percent';
        currency?: string;
        locale?: string;
        scale?: number;
        negative?: 'minus' | 'parentheses';
    }>(),
    { negative: 'minus' },
);
const page = usePage();
const defaults = computed(() =>
    numberDefaults(
        page.props.kitNumber as
            { locale?: string; currency?: string } | undefined,
        typeof navigator === 'undefined' ? null : navigator.language,
    ),
);
const codec = computed(() =>
    numberFormat({
        kind: props.kind,
        locale: props.locale ?? defaults.value.locale,
        currency: props.currency ?? defaults.value.currency,
        scale: props.scale,
        signed: true,
        negative: props.negative,
    }),
);
const shown = computed(() => {
    if (props.value === null || props.value === undefined || props.value === '')
        return null;
    const negative = props.value.startsWith('-') && /[1-9]/.test(props.value);
    const number = codec.value.format(props.value);
    const bare =
        negative && props.negative === 'parentheses'
            ? number.slice(1, -1)
            : negative
              ? number.slice(1)
              : number;
    const { prefix, suffix } = codec.value.affix;
    return { negative, text: `${prefix}${bare}${suffix}` };
});
</script>
<template>
    <span
        v-if="shown"
        class="tabular-nums"
        :data-negative="shown.negative || undefined"
        data-amount
    >
        <template v-if="shown.negative && negative === 'parentheses'"
            ><span class="sr-only">negative </span
            ><span aria-hidden="true">(</span>{{ shown.text
            }}<span aria-hidden="true">)</span></template
        >
        <template v-else-if="shown.negative">−{{ shown.text }}</template>
        <template v-else>{{ shown.text }}</template>
    </span>
</template>
