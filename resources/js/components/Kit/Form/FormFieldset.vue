<script setup lang="ts">
import { computed, useId, useSlots } from 'vue';
import { useLockedNotice, useNoticeOwned } from './useLocked';

// A titled group of fields inside a FormCard (the FormFieldset spec): a native fieldset whose legend holds the title as a real heading at `level`, so the
// group is named for assistive technology and heading navigation still finds it. The description sits after the legend and describes the fieldset
// (aria-describedby), so it is not part of the group's name. `legendHidden` keeps both for screen readers only. Groups after the first are set apart by
// `separator`: a rule (default) or space, decided by position among sibling fieldsets (a CSS sibling rule), so reordering groups needs no change. The grid is
// six columns from `sm` and one below; `fullwidth` stacks the fields whatever their spans. Attributes land on the fieldset (`class="col-span-full"`).
// Locking comes from LockedForm only (no prop here, and never `<fieldset disabled>`); a lone fieldset in its own area shows the notice before its grid.
// `legend` is the old name of `title`, kept as an alias until 0.4.
const props = withDefaults(defineProps<{ title?: string; legend?: string; description?: string; level?: 2 | 3 | 4 | 5 | 6; legendHidden?: boolean; separator?: 'rule' | 'space'; fullwidth?: boolean }>(), { title: undefined, legend: undefined, description: undefined, level: 2, legendHidden: false, separator: 'rule', fullwidth: false });
const slots = useSlots();
const name = computed(() => props.title ?? props.legend);
const named = computed(() => Boolean(name.value || slots.title));
if (!named.value && import.meta.env?.DEV) console.warn('<FormFieldset> has no title: a group without a name should not be a fieldset.');
const id = useId();
const describedBy = computed(() => (props.description || slots.description ? `${id}-description` : undefined));
// Whole class strings (Tailwind scans the source): the separator applies only to a fieldset preceded by a sibling fieldset.
const separators = { rule: '[fieldset~&]:mt-8 [fieldset~&]:border-t [fieldset~&]:border-border [fieldset~&]:pt-8', space: '[fieldset~&]:mt-12' } as const;
const owned = useNoticeOwned();
const lock = useLockedNotice(!owned);
const notice = computed(() => lock.locked && !owned);
</script>
<template>
    <fieldset :class="['m-0 min-w-0 border-0 p-0', separators[separator]]" :aria-describedby="describedBy" data-form-fieldset :data-separator="separator">
        <legend v-if="named" :class="['float-left w-full p-0', legendHidden ? 'sr-only' : notice || describedBy ? '' : 'mb-6']" data-form-legend>
            <component :is="`h${level}`" class="text-base leading-7 font-semibold text-foreground"><slot name="title">{{ name }}</slot></component>
        </legend>
        <p v-if="describedBy" :id="describedBy" :class="['clear-both text-sm leading-6 text-muted-foreground', legendHidden ? 'sr-only' : notice ? 'mt-1' : 'mt-1 mb-6']" data-form-description><slot name="description">{{ description }}</slot></p>
        <div v-if="notice" :id="lock.id" class="clear-both mb-6 text-sm text-muted-foreground" :class="describedBy && !legendHidden ? 'mt-3' : ''" data-form-notice>
            <p>{{ lock.notice }}</p>
            <p v-if="lock.reason" class="mt-1" data-form-notice-reason>{{ lock.reason }}</p>
        </div>
        <div :class="['clear-both grid gap-x-6 gap-y-8', fullwidth ? 'grid-cols-1 *:col-span-full!' : 'grid-cols-6 *:col-span-6']" data-form-fieldset-grid><slot /></div>
    </fieldset>
</template>
