<script setup lang="ts">
import { type HTMLAttributes } from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import { cn } from '@/components/Kit/utils';
import SectionHeader from './SectionHeader.vue';

/*
 * One titled region of a page, a direct child of <Page> (or of a PageGrid cell): a <section> named by its title, with an optional SectionHeader (`title`,
 * `description`, descriptor `actions`, heading `level`, 2 by default) above its content, which it stacks with a Stack's rhythm (`gap-4`). It carries no outer
 * margin: the Page's gap separates it from its siblings. With no `title` it is a plain stacked group; pass `aria-label` to name it. `action` is emitted as
 * SectionHeader emits it.
 */
const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string | null;
        actions?: ActionDescriptor[];
        level?: 2 | 3 | 4 | 5 | 6;
        class?: HTMLAttributes['class'];
    }>(),
    {
        title: undefined,
        description: null,
        actions: () => [],
        level: 2,
        class: undefined,
    },
);
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
</script>
<template>
    <section
        :aria-label="title"
        :class="cn('flex flex-col gap-4', props.class)"
        data-slot="page-section"
    >
        <SectionHeader
            v-if="title"
            :title="title"
            :description="description"
            :actions="actions"
            :level="level"
            @action="(action: ActionDescriptor) => emit('action', action)"
        />
        <slot />
    </section>
</template>
