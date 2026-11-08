<script setup lang="ts">
import { type Component, useId } from 'vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import { groundCard, useGroundSurface } from '@/components/Kit/Surface/surface';

/*
 * Nothing here yet: an icon, a title, a line of body and what to do next. The next step is the `action` slot (a kit Button) or server `actions` descriptors (an
 * ActionBar, whose `action` event is re-emitted). `compact` is the small form for a panel, a list or a table body; the default is centred for a whole page section.
 * It is a plain region labelled by its title, never a live region: it is there when the view renders, so announcing it would be noise. The `icon` slot replaces
 * the `icon` component, and the default slot the `body` text. Utilities and tokens only. On a tinted page ground it draws its own card (README, "Tinted page
 * grounds"), unless it already sits on one.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        body?: string | null;
        icon?: Component | null;
        actions?: ActionDescriptor[];
        compact?: boolean;
        level?: 2 | 3 | 4 | 5 | 6;
    }>(),
    { body: null, icon: null, actions: () => [], compact: false, level: 3 },
);
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
// ActionBar hands back its own copy of a descriptor; the host gets the one it passed.
const original = (action: ActionDescriptor) =>
    (action.key
        ? props.actions.find((item) => item.key === action.key)
        : undefined) ?? action;
const titleId = `empty-state-${useId()}`;
const surface = useGroundSurface();
</script>
<template>
    <section
        :aria-labelledby="titleId"
        :class="[
            'grid min-w-0 justify-items-center text-center',
            compact ? 'gap-2 px-4 py-6' : 'gap-3 px-6 py-12',
            surface && groundCard,
        ]"
        :data-compact="compact || undefined"
        :data-ground-surface="surface || undefined"
        data-empty-state
    >
        <div
            v-if="$slots.icon || props.icon"
            :class="[
                'bg-muted text-muted-foreground flex items-center justify-center rounded-full',
                compact ? 'size-9' : 'size-12',
            ]"
            aria-hidden="true"
            data-empty-state-icon
        >
            <slot name="icon"
                ><component
                    :is="props.icon"
                    :class="compact ? 'size-4' : 'size-6'"
            /></slot>
        </div>
        <component
            :is="`h${level}`"
            :id="titleId"
            :class="[
                'text-foreground m-0 font-semibold',
                compact ? 'text-sm' : 'text-base',
            ]"
            >{{ title }}</component
        >
        <div
            v-if="$slots.default || body"
            :class="[
                'text-muted-foreground max-w-prose',
                compact ? 'text-xs' : 'text-sm',
            ]"
            data-empty-state-body
        >
            <slot>{{ body }}</slot>
        </div>
        <div
            v-if="$slots.action || actions.length"
            :class="[
                'flex flex-wrap items-center justify-center gap-2',
                compact ? 'pt-1' : 'pt-2',
            ]"
            data-empty-state-action
        >
            <slot name="action" />
            <ActionBar
                v-if="actions.length"
                :actions="actions"
                @action="(action) => emit('action', original(action))"
            />
        </div>
    </section>
</template>
