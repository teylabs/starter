<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
import ActivityEntryRow from './ActivityEntryRow.vue';
import { activitySections, type ActivityEntry } from './activity';

/*
 * A read-only stream of activity entries, newest first, optionally cut into days (`days`: a section per `entry.day`, labelled by the day). It does not know where
 * the entries came from. Paging is the host's (useCursorFeed): pass `more` and `loading`, and handle `load-more`. Pressing "Load more" keeps focus on the button
 * while more remain; once the last page arrives, focus moves to the first new entry. Not a live region unless `live` is set (a feed the viewer watches update).
 */
const props = withDefaults(
    defineProps<{
        entries: ActivityEntry[];
        days?: boolean;
        empty?: string;
        dense?: boolean;
        live?: boolean;
        more?: boolean;
        loading?: boolean;
    }>(),
    {
        days: true,
        empty: 'Nothing has happened yet.',
        dense: false,
        live: false,
        more: false,
        loading: false,
    },
);
const emit = defineEmits<{ 'load-more': [] }>();
const sections = computed(() => activitySections(props.entries, props.days));
const region = ref<HTMLElement>();
let pressed = false;
let before = props.entries.length;

function loadMore() {
    pressed = true;
    before = props.entries.length;
    emit('load-more');
}
watch(
    () => props.entries.length,
    async (count) => {
        if (pressed && count > before && !props.more) {
            await nextTick();
            region.value
                ?.querySelectorAll<HTMLElement>(
                    ':scope > section > ul > [data-activity-entry]',
                )
                [before]?.focus();
        }
        if (!props.loading) pressed = false;
    },
);
</script>
<template>
    <EmptyState v-if="!entries.length" :title="empty" compact />
    <div
        v-else
        ref="region"
        :class="dense ? 'space-y-3' : 'space-y-6'"
        :aria-live="live ? 'polite' : undefined"
        :data-dense="dense || undefined"
        data-activity-stream
    >
        <section
            v-for="section in sections"
            :key="section.key"
            :aria-label="section.day?.label"
            data-activity-day
        >
            <h2
                v-if="section.day"
                class="text-muted-foreground mb-1 text-xs font-semibold tracking-wide uppercase"
            >
                {{ section.day.label }}
            </h2>
            <ul class="divide-border divide-y">
                <ActivityEntryRow
                    v-for="entry in section.entries"
                    :key="entry.id"
                    :entry="entry"
                    :dense="dense"
                />
            </ul>
        </section>
        <div v-if="more" class="flex justify-center pt-2">
            <Button
                type="button"
                variant="secondary"
                :disabled="loading"
                data-load-more
                @click="loadMore"
                >{{ loading ? 'Loading…' : 'Load more' }}</Button
            >
        </div>
    </div>
</template>
