<script setup lang="ts">
import { computed } from 'vue';
import DateTime from '@/components/Kit/Data/DateTime.vue';
import Collapsible from '@/components/Kit/Layout/Collapsible.vue';
import {
    activityTone,
    headlineText,
    trustedHtml,
    type ActivityEntry,
} from './activity';

/*
 * One entry of an ActivityStream: a tone dot (decoration; the headline carries the meaning), the headline, then the time and the meta facts. A group's children
 * open in a kit Collapsible. The headline's `html` reaches the DOM only through trustedHtml(): the host's server escaped it, and the kit does not.
 */
const props = withDefaults(
    defineProps<{ entry: ActivityEntry; nested?: boolean; dense?: boolean }>(),
    { nested: false, dense: false },
);
// Whole class strings so Tailwind finds them.
const dots = {
    success: 'bg-(--kit-tone-success-fg)',
    warning: 'bg-(--kit-tone-warning-fg)',
    danger: 'bg-(--kit-tone-error-fg)',
    info: 'bg-(--kit-tone-info-fg)',
    neutral: 'bg-muted-foreground/40',
} as const;
const html = computed(() => trustedHtml(props.entry.headline));
const text = computed(() => headlineText(props.entry.headline));
// A host's own intent vocabulary shows as neutral; in development, once per intent, it says so.
const warned = new Set<string>();
const tone = computed(() =>
    activityTone(props.entry.intent, (intent) => {
        if (!import.meta.env?.DEV || warned.has(intent)) return;
        warned.add(intent);
        console.warn(
            `ActivityStream: intent "${intent}" is not a kit tone (success, warning, danger, info, neutral), so it shows as neutral.`,
        );
    }),
);
const when = computed(
    () => props.entry.timestamp ?? props.entry.occurredAt ?? null,
);
const total = computed(() => props.entry.count ?? props.entry.children.length);
</script>
<template>
    <li
        :class="[
            'flex gap-3 outline-none',
            nested || dense ? 'py-1.5' : 'py-3',
        ]"
        tabindex="-1"
        data-activity-entry
        :data-kind="entry.kind"
        :data-tone="tone"
    >
        <span
            :class="['mt-2 size-2 shrink-0 rounded-full', dots[tone]]"
            aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
            <!-- eslint-disable-next-line vue/no-v-html -- server-escaped by the host (ActivityHeadline) -->
            <p
                v-if="html !== null"
                class="text-foreground [&_a]:decoration-border [&_a:hover]:decoration-foreground text-sm leading-6 [&_a]:font-medium [&_a]:underline [&_a]:underline-offset-4"
                data-activity-headline="html"
                v-html="html"
            />
            <p
                v-else-if="text"
                class="text-foreground text-sm leading-6"
                data-activity-headline="text"
            >
                {{ text }}
            </p>
            <p
                v-if="when || entry.meta.length || entry.effectiveOnLabel"
                :class="[
                    'text-muted-foreground flex flex-wrap gap-x-2 text-xs',
                    (html !== null || text) && 'mt-0.5',
                ]"
            >
                <span v-if="entry.effectiveOnLabel" data-activity-effective
                    >effective
                    <time :datetime="entry.effectiveOn ?? undefined">{{
                        entry.effectiveOnLabel
                    }}</time
                    ><template v-if="when"> · recorded</template></span
                >
                <time
                    v-if="entry.timestamp"
                    :datetime="entry.occurredAt ?? undefined"
                    :title="entry.timestampTitle ?? undefined"
                    >{{ entry.timestamp }}</time
                >
                <DateTime
                    v-else-if="entry.occurredAt"
                    :value="entry.occurredAt"
                />
                <span
                    v-for="fact in entry.meta"
                    :key="fact.label"
                    data-activity-meta
                >
                    <span class="sr-only">{{ fact.label }}: </span
                    ><span
                        aria-hidden="true"
                        v-if="
                            when ||
                            entry.effectiveOnLabel ||
                            fact !== entry.meta[0]
                        "
                        >· </span
                    >{{ fact.value }}
                </span>
            </p>
            <Collapsible
                v-if="entry.kind === 'group' && entry.children.length"
                :level="4"
                class="mt-1 border-b-0"
                data-activity-group
            >
                <template #summary="{ open }">{{
                    open ? 'Show fewer' : `Show all ${total}`
                }}</template>
                <ul class="border-border border-l pl-3">
                    <ActivityEntryRow
                        v-for="child in entry.children"
                        :key="child.id"
                        :entry="child"
                        nested
                    />
                    <li
                        v-if="entry.truncated"
                        class="text-muted-foreground py-1.5 text-xs"
                    >
                        and {{ total - entry.children.length }} more, not shown
                    </li>
                </ul>
            </Collapsible>
        </div>
    </li>
</template>
