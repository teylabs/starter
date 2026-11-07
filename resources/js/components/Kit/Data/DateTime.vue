<script setup lang="ts">
import { computed } from 'vue';
import { type DateTimeFormat, formatDateTime, fullDateTime } from './dateTime';
import { useMinuteTicker, useTimezone, viewerLocale } from './useTimezone';

/*
 * A date or time shown to the viewer: a <time> with the machine value in `datetime` and the full date (and time, with its zone) in `title`. `value` is a calendar
 * date (`YYYY-MM-DD`, never converted between zones) or an instant with its offset (RFC 3339, shown in the viewer's timezone, `kitTimezone`). `format`:
 * `local` (date and time, the default), `relative` ("3 minutes ago", absolute past `relative-until-days`), `date`, `time`, `ymd` (2026-10-29) or `datetime`.
 * `live` re-renders a relative time each minute on one shared ticker. `timezone`, `locale` and `now` override the viewer's (tests and server-chosen zones).
 */
const props = withDefaults(
    defineProps<{
        value: string;
        format?: DateTimeFormat;
        timezone?: string;
        locale?: string;
        now?: number | Date;
        live?: boolean;
        relativeUntilDays?: number;
    }>(),
    {
        format: 'local',
        timezone: undefined,
        locale: undefined,
        now: undefined,
        live: false,
        relativeUntilDays: 7,
    },
);
const viewerZone = useTimezone();
const tick = props.live ? useMinuteTicker() : null;
const options = computed(() => ({
    timezone: props.timezone ?? viewerZone.value,
    locale: props.locale ?? viewerLocale(),
    now: props.now ?? tick?.value ?? Date.now(),
    relativeUntilDays: props.relativeUntilDays,
}));
const text = computed(() =>
    formatDateTime(props.value, props.format, options.value),
);
const title = computed(() => fullDateTime(props.value, options.value));
</script>
<template>
    <time :datetime="value" :title="title" data-slot="date-time">{{
        text
    }}</time>
</template>
