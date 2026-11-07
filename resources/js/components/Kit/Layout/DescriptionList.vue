<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';

export type DescriptionItem = {
    term: string;
    description: string | number | null;
    key?: string;
    copy?: boolean;
};

/*
 * Term and description pairs (a record's details, a connection's metadata) as a real <dl>: each pair a <div> holding one <dt> and one <dd>. From `items`, or the
 * default slot for pairs the host writes itself (keep its children <div><dt/><dd/></div>); the `description` slot renders one item's value. `inline` puts the term
 * beside its description from `sm` up and stacks them on a phone; `stacked` always stacks. An empty value shows `empty` (an em dash). `copy` on an item, or
 * `copyable` on the list, adds a button that copies the value; the result is spoken once through a polite status beside it. Utilities and tokens only.
 */
const props = withDefaults(
    defineProps<{
        items?: DescriptionItem[];
        layout?: 'inline' | 'stacked';
        copyable?: boolean;
        empty?: string;
    }>(),
    { items: () => [], layout: 'inline', copyable: false, empty: '—' },
);
const copied = ref<string | null>(null);
const failed = ref<string | null>(null);
const keyOf = (item: DescriptionItem, index: number) =>
    item.key ?? `${index}:${item.term}`;
const blank = (item: DescriptionItem) =>
    item.description === null || item.description === '';
const canCopy = (item: DescriptionItem) =>
    !blank(item) && (item.copy ?? props.copyable);
async function copy(item: DescriptionItem, index: number): Promise<void> {
    const key = keyOf(item, index);
    try {
        await navigator.clipboard.writeText(String(item.description));
        copied.value = key;
        failed.value = null;
    } catch {
        copied.value = null;
        failed.value = key;
    }
}
</script>
<template>
    <dl
        :class="[
            'grid min-w-0 text-sm',
            layout === 'inline' ? 'gap-y-3 sm:gap-y-2' : 'gap-y-3',
        ]"
        :data-layout="layout"
        data-description-list
    >
        <slot>
            <div
                v-for="(item, index) in items"
                :key="keyOf(item, index)"
                :class="[
                    'grid min-w-0 gap-1',
                    layout === 'inline' &&
                        'sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] sm:gap-4',
                ]"
                data-description-item
            >
                <dt class="text-muted-foreground min-w-0 font-medium">
                    {{ item.term }}
                </dt>
                <dd class="text-foreground m-0 flex min-w-0 items-start gap-2">
                    <span class="min-w-0 break-words"
                        ><slot name="description" :item="item">{{
                            blank(item) ? empty : item.description
                        }}</slot></span
                    >
                    <template v-if="canCopy(item)">
                        <button
                            type="button"
                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-ring -my-1 inline-flex size-7 shrink-0 items-center justify-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2"
                            :aria-label="`Copy ${item.term}`"
                            data-description-copy
                            @click="copy(item, index)"
                        >
                            <Check
                                v-if="copied === keyOf(item, index)"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <Copy v-else class="size-4" aria-hidden="true" />
                        </button>
                        <span role="status" class="sr-only">{{
                            copied === keyOf(item, index)
                                ? `${item.term} copied`
                                : failed === keyOf(item, index)
                                  ? `Could not copy ${item.term}`
                                  : ''
                        }}</span>
                    </template>
                </dd>
            </div>
        </slot>
    </dl>
</template>
