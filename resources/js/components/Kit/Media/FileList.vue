<script setup lang="ts">
import InertiaAction from '@/components/Kit/Action/InertiaAction.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
import { fileExtension, fileSize } from '@/components/Kit/Media/fileList';

export type FileListItem = {
    id: string | number;
    name: string;
    url: string;
    size?: string | number | null;
    mime?: string | null;
    thumb?: string | null;
    actions?: ActionDescriptor[];
};

/*
 * The files a record holds, read-only, as an attachment list (docs/media.md): one row per file with a thumbnail or an extension badge, the name as a link, and its
 * size and type. Each row's `actions` are the server's named actions (View, Download, Delete), each an InertiaAction, so a destructive one confirms first; an
 * absent action is one the server did not allow. The `#actions` slot (scoped by `item`) replaces them. With no files it shows an EmptyState (`emptyText`).
 * Every URL comes from the item: the client builds none. For uploading, reordering or moving files, use MediaCollection.
 */
withDefaults(
    defineProps<{
        items: readonly FileListItem[];
        emptyText?: string;
        label?: string;
    }>(),
    { emptyText: 'No files yet.', label: 'Files' },
);
</script>

<template>
    <ul
        v-if="items.length"
        :aria-label="label"
        data-file-list
        class="divide-border border-border divide-y rounded-md border text-sm"
    >
        <li
            v-for="item in items"
            :key="item.id"
            class="flex items-center gap-3 px-3 py-2"
        >
            <img
                v-if="item.thumb"
                :src="item.thumb"
                alt=""
                class="ring-border size-10 shrink-0 rounded-md object-cover ring-1"
            />
            <span
                v-else
                aria-hidden="true"
                class="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-md text-xs"
                >{{ fileExtension(item.name) }}</span
            >
            <div class="min-w-0 flex-1">
                <a
                    :href="item.url"
                    :title="item.name"
                    class="text-foreground block truncate font-medium underline"
                    >{{ item.name }}</a
                >
                <p
                    v-if="fileSize(item.size) || item.mime"
                    class="text-muted-foreground text-xs"
                >
                    {{
                        [fileSize(item.size), item.mime]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <slot name="actions" :item="item">
                    <InertiaAction
                        v-for="action in item.actions ?? []"
                        :key="action.key ?? action.label"
                        :action="action"
                        size="sm"
                        variant="ghost"
                        :class="action.destructive && 'text-destructive-text'"
                    />
                </slot>
            </div>
        </li>
    </ul>
    <EmptyState v-else :title="emptyText" compact />
</template>
