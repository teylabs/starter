/*
 * Dates and times for display, with the browser's Intl only (no date library). Two kinds of value, never mixed:
 * - a calendar date, `YYYY-MM-DD`: a day on the calendar, never converted between zones (the 1st of November is the 1st everywhere, through any DST change);
 * - an instant, RFC 3339 with its offset (`2026-10-29T18:20:00-03:00` or `…Z`): shown in the viewer's timezone (`kitTimezone`, see useTimezone).
 * An instant without an offset is ambiguous, so it is not guessed: it is shown as given.
 */
export type DateTimeFormat =
    'relative' | 'local' | 'datetime' | 'date' | 'time' | 'ymd';
export type DateTimeOptions = {
    timezone?: string;
    locale?: string;
    now?: Date | number;
    relativeUntilDays?: number;
};

const CALENDAR_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const INSTANT =
    /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:\d{2})$/i;

/** A calendar date as a UTC timestamp for formatting in UTC, so the day never moves. */
function calendarDay(value: string): number | null {
    const match = CALENDAR_DATE.exec(value);
    if (!match) return null;
    const time = Date.UTC(
        Number(match[1]),
        Number(match[2]) - 1,
        Number(match[3]),
    );
    const check = new Date(time);
    return check.getUTCFullYear() === Number(match[1]) &&
        check.getUTCMonth() === Number(match[2]) - 1 &&
        check.getUTCDate() === Number(match[3])
        ? time
        : null;
}

export function isCalendarDate(value: string): boolean {
    return calendarDay(value) !== null;
}

/** Today's calendar date (`YYYY-MM-DD`) in a timezone. */
export function todayIn(
    timezone: string,
    now: Date | number = Date.now(),
): string {
    return ymdIn(new Date(now), timezone);
}

function ymdIn(date: Date, timezone: string): string {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        })
            .formatToParts(date)
            .map((part) => [part.type, part.value]),
    );
    return `${parts.year}-${parts.month}-${parts.day}`;
}

function relative(seconds: number, locale: string): string {
    const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    const size = Math.abs(seconds);
    if (size < 45) return format.format(0, 'second');
    if (size < 45 * 60)
        return format.format(Math.round(seconds / 60), 'minute');
    if (size < 22 * 3600)
        return format.format(Math.round(seconds / 3600), 'hour');
    return format.format(Math.round(seconds / 86400), 'day');
}

/** The value as display text. Unknown or ambiguous input comes back unchanged. */
export function formatDateTime(
    value: string,
    format: DateTimeFormat = 'local',
    options: DateTimeOptions = {},
): string {
    const timezone = options.timezone ?? 'UTC';
    const locale = options.locale ?? 'en';
    const now = options.now === undefined ? Date.now() : Number(options.now);
    const until = (options.relativeUntilDays ?? 7) * 86400;
    const day = calendarDay(value);
    if (day !== null) {
        if (format === 'ymd') return value;
        if (format === 'relative') {
            const days = Math.round(
                (day - (calendarDay(todayIn(timezone, now)) ?? day)) / 86400000,
            );
            return Math.abs(days) * 86400 > until
                ? formatDateTime(value, 'date', options)
                : new Intl.RelativeTimeFormat(locale, {
                      numeric: 'auto',
                  }).format(days, 'day');
        }
        // `time` has no meaning for a date; every other format shows the date. Formatted in UTC, where the timestamp was made: the day cannot shift.
        return new Intl.DateTimeFormat(locale, {
            dateStyle: 'medium',
            timeZone: 'UTC',
        }).format(day);
    }
    if (!INSTANT.test(value) || Number.isNaN(Date.parse(value))) return value;
    const instant = new Date(value);
    switch (format) {
        case 'ymd':
            return ymdIn(instant, timezone);
        case 'date':
            return new Intl.DateTimeFormat(locale, {
                dateStyle: 'medium',
                timeZone: timezone,
            }).format(instant);
        case 'time':
            return new Intl.DateTimeFormat(locale, {
                timeStyle: 'short',
                timeZone: timezone,
            }).format(instant);
        case 'relative': {
            const seconds = (instant.getTime() - now) / 1000;
            return Math.abs(seconds) > until
                ? formatDateTime(value, 'local', options)
                : relative(seconds, locale);
        }
        default:
            return new Intl.DateTimeFormat(locale, {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: timezone,
            }).format(instant);
    }
}

/** The full value for a title: the long date, and for an instant the time with its zone. */
export function fullDateTime(
    value: string,
    options: DateTimeOptions = {},
): string {
    const day = calendarDay(value);
    if (day !== null)
        return new Intl.DateTimeFormat(options.locale ?? 'en', {
            dateStyle: 'full',
            timeZone: 'UTC',
        }).format(day);
    if (!INSTANT.test(value) || Number.isNaN(Date.parse(value))) return value;
    return new Intl.DateTimeFormat(options.locale ?? 'en', {
        dateStyle: 'full',
        timeStyle: 'long',
        timeZone: options.timezone ?? 'UTC',
    }).format(new Date(value));
}
