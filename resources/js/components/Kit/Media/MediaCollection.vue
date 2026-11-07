<script setup lang="ts">
import { MoreHorizontal } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DropdownMenuContent from '@/components/Kit/Menu/MenuContent.vue';
import SortableList from '@/components/Kit/Data/SortableList.vue';
import type { ReorderAction } from '@/components/Kit/Data/sortable';
import BulkDrop from '@/components/Kit/Form/BulkDrop.vue';
import type { DirectRoutes } from '@/components/Kit/Form/directUpload';

export type MediaItem = {
    id: string;
    name: string;
    mime: string;
    size: number;
    src: string;
    srcset: string;
    width: number | null;
    height: number | null;
};
export type MediaCollectionData = {
    collection: string;
    version: string;
    items: MediaItem[];
};

/*
 * One record's photos or files (docs/media.md, "A record's collection"), from the server's MediaCollection::for: a single file (`single`) or an ordered set.
 * Files are added with BulkDrop's direct upload (`direct`), so no bytes go through the form. An ordered set reorders with SortableList (drag, keys and Move up/down
 * buttons), sent with `reorder` and the collection's version, and rolls back on a stale one (`conflict`). Each item's menu moves it to another collection
 * (`move-targets`) and removes it; those are emitted for the host to run (its own route, which calls MoveMedia / RemoveMedia, and a confirmation for remove).
 * Words come from `labels`. Nothing here decides what a record may hold: the server's rules do.
 */
const props = withDefaults(
    defineProps<{
        collection: MediaCollectionData;
        label: string;
        accept: string[];
        maxBytes: number;
        single?: boolean;
        direct?: DirectRoutes | null;
        reorder?: ReorderAction | null;
        moveTargets?: { collection: string; label: string }[];
        disabled?: boolean;
        labels?: Partial<
            Record<'item' | 'empty' | 'actions' | 'remove' | 'moveTo', string>
        >;
        /** `grid` lays photos out as tiles (the image above its name and menu), as SortableList's layout. */
        layout?: 'list' | 'grid' | 'row';
        /** A divider after this many photos, e.g. the ones a listing shows. */
        cutAfter?: number | null;
        cutLabel?: string;
    }>(),
    {
        layout: 'list',
        cutAfter: null,
        cutLabel: undefined,
        single: false,
        direct: null,
        reorder: null,
        moveTargets: () => [],
        disabled: false,
        labels: () => ({}),
    },
);
const emit = defineEmits<{
    uploaded: [results: unknown[]];
    move: [item: MediaItem, to: string];
    remove: [item: MediaItem];
    saved: [version: string | number | null];
    conflict: [order: string[]];
}>();
const text = computed(() => ({
    item: 'Photo',
    empty: 'Nothing here yet.',
    actions: 'Actions for',
    remove: 'Remove',
    moveTo: 'Move to',
    ...props.labels,
}));
const items = computed(() => props.collection.items);
const targets = computed(() =>
    props.moveTargets.filter(
        (target) => target.collection !== props.collection.collection,
    ),
);
// A single-file collection takes one more file only while it is empty; replacing is a remove, then an add.
const canAdd = computed(
    () =>
        !props.disabled &&
        !!props.direct &&
        (!props.single || items.value.length === 0),
);
const itemLabel = (item: MediaItem, index: number) =>
    `${text.value.item} ${index + 1}${item.name ? ` (${item.name})` : ''}`;
</script>
<template>
    <section
        class="grid gap-3"
        :aria-label="label"
        :data-media-collection="collection.collection"
    >
        <p
            v-if="items.length === 0"
            class="text-muted-foreground text-sm"
            data-media-empty
        >
            {{ text.empty }}
        </p>
        <SortableList
            v-else-if="!single"
            :items="items"
            :item-label="itemLabel"
            :label="label"
            :reorder="reorder"
            :version="collection.version"
            :disabled="disabled"
            :layout="layout"
            :cut-after="cutAfter"
            :cut-label="cutLabel"
            @saved="emit('saved', $event)"
            @conflict="emit('conflict', $event)"
        >
            <template #default="{ item, index }">
                <div
                    :class="
                        layout === 'list'
                            ? 'flex min-w-0 items-center gap-3'
                            : 'grid min-w-0 grid-cols-[1fr_auto] items-center gap-2'
                    "
                    :data-media-item="item.id"
                >
                    <img
                        :src="item.src"
                        :srcset="item.srcset || undefined"
                        :sizes="layout === 'list' ? '4rem' : '10rem'"
                        :width="item.width ?? undefined"
                        :height="item.height ?? undefined"
                        :alt="itemLabel(item, index)"
                        loading="lazy"
                        :class="
                            layout === 'list'
                                ? 'border-border size-16 shrink-0 rounded-md border object-cover'
                                : 'border-border col-span-2 aspect-square w-full rounded-md border object-cover'
                        "
                    />
                    <span class="min-w-0 flex-1 truncate text-sm">{{
                        item.name
                    }}</span>
                    <DropdownMenu v-if="!disabled">
                        <DropdownMenuTrigger as-child
                            ><button
                                type="button"
                                class="hover:bg-accent focus-visible:outline-ring inline-flex size-8 items-center justify-center rounded-md focus-visible:outline-2"
                                :aria-label="`${text.actions} ${itemLabel(item, index)}`"
                            >
                                <MoreHorizontal
                                    class="size-4"
                                    aria-hidden="true"
                                /></button
                        ></DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                v-for="target in targets"
                                :key="target.collection"
                                :data-media-move="target.collection"
                                @select="emit('move', item, target.collection)"
                                >{{ text.moveTo }}
                                {{ target.label }}</DropdownMenuItem
                            >
                            <DropdownMenuItem
                                class="text-destructive-text focus:text-destructive-text"
                                data-media-remove
                                @select="emit('remove', item)"
                                >{{ text.remove }}</DropdownMenuItem
                            >
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>
        </SortableList>
        <div
            v-else
            class="flex min-w-0 items-center gap-3"
            :data-media-item="items[0].id"
        >
            <img
                :src="items[0].src"
                :srcset="items[0].srcset || undefined"
                sizes="8rem"
                :width="items[0].width ?? undefined"
                :height="items[0].height ?? undefined"
                :alt="label"
                class="border-border size-32 shrink-0 rounded-md border object-cover"
            />
            <div v-if="!disabled" class="flex flex-wrap gap-2">
                <button
                    v-for="target in targets"
                    :key="target.collection"
                    type="button"
                    class="text-sm underline underline-offset-2"
                    :data-media-move="target.collection"
                    @click="emit('move', items[0], target.collection)"
                >
                    {{ text.moveTo }} {{ target.label }}
                </button>
                <button
                    type="button"
                    class="text-destructive-text text-sm underline underline-offset-2"
                    data-media-remove
                    @click="emit('remove', items[0])"
                >
                    {{ text.remove }}
                </button>
            </div>
        </div>
        <BulkDrop
            v-if="canAdd && direct"
            :name="collection.collection"
            :label="label"
            :accept="accept"
            :max-bytes="maxBytes"
            :has-files="items.length > 0"
            :multiple="!single"
            :direct="direct"
            compact
            @uploaded="emit('uploaded', $event)"
        />
    </section>
</template>
