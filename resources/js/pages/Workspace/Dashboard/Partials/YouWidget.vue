<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Avatar, Checklist, type ChecklistItem } from '@kit/ui';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { WidgetFrame } from './types';

// The "You" card: the viewer only, with no title (the card is still named "You" for assistive tech). Avatar, name, their local time and timezone (a permanent
// read-out, never a to-do) with Edit, then their own onboarding steps (profile photo, timezone) while any is still open. `email`, `role` and `workspace` are
// still sent for an app's own card.
type Person = { name: string; email: string; initials: string; avatar: { url: string; url_2x: string } | null; role: string | null; workspace: string; timezone?: string };
const props = defineProps<{ widget: WidgetFrame & { person: Person; checklist?: ChecklistItem[] } }>();
const zone = computed(() => props.widget.person.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone);
const now = ref(new Date());
// The clock turns with the minute: the first tick lands on the next minute boundary, then one every 60s. Both timers are cleared on unmount.
let first: number | undefined;
let tick: number | undefined;
onMounted(() => {
    now.value = new Date();
    first = window.setTimeout(() => {
        now.value = new Date();
        tick = window.setInterval(() => (now.value = new Date()), 60_000);
    }, 60_000 - (Date.now() % 60_000));
});
onBeforeUnmount(() => {
    window.clearTimeout(first);
    window.clearInterval(tick);
});
const time = computed(() => {
    try {
        return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', timeZone: zone.value }).format(now.value);
    } catch {
        return '';
    }
});
const edit = computed(() => props.widget.actions.find((action) => action.key === 'profile') ?? props.widget.actions[0] ?? null);
const open = computed(() => (props.widget.checklist ?? []).some((item) => item.state !== 'done'));
</script>
<template>
    <div class="grid gap-4">
        <div class="flex min-w-0 items-center gap-3" data-widget-you>
            <Avatar :initials="widget.person.initials" :src="widget.person.avatar?.url" :src2x="widget.person.avatar?.url_2x" large />
            <div class="grid min-w-0 flex-1">
                <p class="truncate font-semibold" data-widget-you-name>{{ widget.person.name }}</p>
                <p class="text-sm text-pretty wrap-anywhere text-muted-foreground" data-widget-you-time><span v-if="time">{{ time }}</span><template v-if="time"> · </template>{{ zone.replace(/_/g, ' ') }}</p>
            </div>
            <Link v-if="edit" :href="edit.url" class="inline-flex min-h-11 shrink-0 items-center rounded-sm text-sm font-medium text-primary underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" data-widget-you-edit>{{ edit.label }}<span class="sr-only"> your profile</span></Link>
        </div>
        <section v-if="open" aria-labelledby="widget-you-setup" class="grid gap-2 border-t pt-4" data-widget-you-setup>
            <h3 id="widget-you-setup" class="text-sm font-medium">Finish setting up</h3>
            <Checklist :items="widget.checklist!" label="Your setup steps" :announce="false" />
        </section>
    </div>
</template>
