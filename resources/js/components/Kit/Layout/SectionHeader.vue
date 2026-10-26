<script setup lang="ts">
import { computed } from 'vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import Avatar from '@/components/Kit/Layout/Avatar.vue';

/*
 * The one header for a section, widget, card or pane: an optional avatar, the title as a real heading at `level` (the page decides the hierarchy) with its count and
 * badge, meta and description under it; the `status` slot and the descriptor actions (ActionBar, the ordinary light style) on the other side. Both sides wrap inside
 * the width. The emitted action is the original descriptor, matched by key. It saves, polls and announces nothing: a host wanting announcements brings a live
 * region in `status`. Wrappers own their gutters and frame. Attributes go on the header.
 */
type Badge = { label: string; variant: string };
type AvatarDescriptor = { initials: string; src?: string | null; src2x?: string | null };
const props = withDefaults(defineProps<{ title: string; count?: number | null; actions?: ActionDescriptor[]; level?: 1 | 2 | 3 | 4 | 5 | 6; badge?: Badge | null; meta?: string | null; description?: string | null; avatar?: AvatarDescriptor | null }>(), { count: null, actions: () => [], level: 2, badge: null, meta: null, description: null, avatar: null });
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
const heading = computed(() => `h${props.level}`);
const original = (action: ActionDescriptor) => (action.key ? props.actions.find(item => item.key === action.key) : undefined) ?? action;
</script>
<template>
    <header class="flex min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-2" data-section-header>
        <div class="flex min-w-0 items-center gap-x-3">
            <Avatar v-if="avatar" :initials="avatar.initials" :src="avatar.src" :src2x="avatar.src2x" />
            <div class="grid min-w-0 gap-0.5">
                <div class="flex min-w-0 items-baseline gap-2">
                    <component :is="heading" class="truncate font-semibold">{{ title }}</component>
                    <span v-if="count !== null" class="shrink-0 rounded-full bg-muted px-2 text-xs font-medium tabular-nums text-muted-foreground" data-section-count>{{ count }}</span>
                    <span v-if="badge" class="inline-flex shrink-0 items-center rounded-md border border-border px-1.5 py-0.5 text-xs font-medium text-muted-foreground" :data-variant="badge.variant" data-section-badge>{{ badge.label }}</span>
                </div>
                <p v-if="meta" class="text-xs text-muted-foreground" data-section-meta>{{ meta }}</p>
                <p v-if="description" class="text-sm text-muted-foreground" data-section-description>{{ description }}</p>
            </div>
        </div>
        <div v-if="$slots.status || actions.length" class="flex min-w-0 flex-wrap items-center justify-end gap-3">
            <slot name="status" />
            <ActionBar v-if="actions.length" :actions="actions" data-section-actions @action="action => emit('action', original(action))" />
        </div>
    </header>
</template>
