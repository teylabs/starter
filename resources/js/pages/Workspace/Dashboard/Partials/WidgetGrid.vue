<script setup lang="ts">
import type { Component } from 'vue';
import ActivityWidget from './ActivityWidget.vue';
import PeopleWidget from './PeopleWidget.vue';
import WidgetCard from './WidgetCard.vue';
import YouWidget from './YouWidget.vue';
import type { WidgetFrame } from './types';

// The responsive widget grid: one column on phones, 6 on tablets, 12 on desktops, each card spanning its widget's `span`. A widget's `component` picks its body:
// the kit's own, or `resources/js/widgets/{Name}.vue` in the app (`queue` renders `widgets/Queue.vue`). An unknown component renders the card with no body.
defineProps<{ widgets: WidgetFrame[] }>();
const own = import.meta.glob<{ default: Component }>('/resources/js/widgets/*.vue', { eager: true });
const kit: Record<string, Component> = { activity: ActivityWidget, people: PeopleWidget, you: YouWidget };
const body = (name: string): Component | undefined => kit[name] ?? own[`/resources/js/widgets/${name.charAt(0).toUpperCase()}${name.slice(1)}.vue`]?.default;
// Literal class names, so Tailwind sees every span.
const md = ['', 'md:col-span-1', 'md:col-span-2', 'md:col-span-3', 'md:col-span-4', 'md:col-span-5', 'md:col-span-6'];
const lg = ['', 'lg:col-span-1', 'lg:col-span-2', 'lg:col-span-3', 'lg:col-span-4', 'lg:col-span-5', 'lg:col-span-6', 'lg:col-span-7', 'lg:col-span-8', 'lg:col-span-9', 'lg:col-span-10', 'lg:col-span-11', 'lg:col-span-12'];
</script>
<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-6 lg:grid-cols-12" data-widget-grid>
        <div v-for="widget in widgets" :key="widget.key" :class="['col-span-1 grid', md[widget.span.md], lg[widget.span.lg]]">
            <WidgetCard :widget="widget">
                <component :is="body(widget.component)" v-if="body(widget.component)" :widget="widget" />
            </WidgetCard>
        </div>
    </div>
</template>
