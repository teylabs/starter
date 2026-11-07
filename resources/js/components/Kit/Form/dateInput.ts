// The pickers' values (InputDate, InputDateRange, InputMonth, InputYear, InputTime, InputTimeRange): plain ISO strings, Intl only.
// A date (`YYYY-MM-DD`), a month (`YYYY-MM`), a year (`YYYY`) and a time (`HH:MM`) are wall values and are never converted between zones.
// A timed value is an instant: it is read and written only through the viewer's zone (`kitTimezone`), and the string it submits carries the offset.

export type DateRange = { start: string; end: string };

const DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const MONTH = /^(\d{4})-(\d{2})$/;
const YEAR = /^(\d{4})$/;
const TIME = /^(\d{2}):(\d{2})(?::(\d{2}))?$/;
const INSTANT =
    /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/;

const pad = (n: number, width = 2) => String(n).padStart(width, '0');
const daysIn = (year: number, month: number) =>
    new Date(Date.UTC(year, month, 0)).getUTCDate();

/** Whether `value` is a real calendar date (`2026-02-30` is not). */
export function isDate(value: string): boolean {
    const m = DATE.exec(value);
    if (!m) return false;
    const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])];

    return mo >= 1 && mo <= 12 && d >= 1 && d <= daysIn(y, mo);
}

export function isMonth(value: string): boolean {
    const m = MONTH.exec(value);

    return Boolean(m) && Number(m![2]) >= 1 && Number(m![2]) <= 12;
}

export function isYear(value: string): boolean {
    return YEAR.test(value) && Number(value) >= 1;
}

/** Whether `value` is a wall time, `HH:MM` or `HH:MM:SS`. */
export function isTime(value: string): boolean {
    const m = TIME.exec(value);

    return (
        Boolean(m) &&
        Number(m![1]) <= 23 &&
        Number(m![2]) <= 59 &&
        Number(m![3] ?? 0) <= 59
    );
}

/** A range is complete when both ends are dates of the same kind and the start is not after the end; an empty range (both ends blank) is valid. */
export function rangeError(
    range: DateRange,
    valid: (value: string) => boolean,
    messages: { incomplete: string; order: string },
): string {
    if (!range.start && !range.end) return '';
    if (!valid(range.start) || !valid(range.end)) return messages.incomplete;

    return range.start <= range.end ? '' : messages.order;
}

/** The offset, in minutes east of UTC, that `timezone` has at the instant `epochMs`. */
export function offsetAt(epochMs: number, timezone: string): number {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            hourCycle: 'h23',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        })
            .formatToParts(new Date(epochMs))
            .map((p) => [p.type, p.value]),
    );
    const wall = Date.UTC(
        Number(parts.year),
        Number(parts.month) - 1,
        Number(parts.day),
        Number(parts.hour),
        Number(parts.minute),
        Number(parts.second),
    );

    return Math.round((wall - Math.floor(epochMs / 1000) * 1000) / 60000);
}

const formatOffset = (minutes: number) =>
    `${minutes < 0 ? '-' : '+'}${pad(Math.floor(Math.abs(minutes) / 60))}:${pad(Math.abs(minutes) % 60)}`;

/**
 * A wall date and time in `timezone` as an RFC 3339 instant with that zone's offset, e.g. `2026-03-08T09:30:00-04:00`.
 * A wall time the zone skips (a spring-forward gap) moves forward by the gap; a repeated one (fall back) takes the earlier instant.
 */
export function toInstant(
    date: string,
    time: string,
    timezone: string,
): string {
    if (!isDate(date) || !isTime(time)) return '';
    const [y, mo, d] = date.split('-').map(Number);
    const [h, mi] = time.split(':').map(Number);
    const wall = Date.UTC(y, mo - 1, d, h, mi);
    // Two passes find the offset in force at the wall time; the earlier candidate wins when both fit (fall back).
    const candidates = [
        offsetAt(wall - 86400000, timezone),
        offsetAt(wall + 86400000, timezone),
    ]
        .map((offset) => wall - offset * 60000)
        .filter(
            (epoch, i, all) =>
                all.indexOf(epoch) === i &&
                offsetAt(epoch, timezone) * 60000 === wall - epoch,
        )
        .sort((a, b) => a - b);
    const epoch = candidates[0] ?? wall - offsetAt(wall, timezone) * 60000;
    const offset = offsetAt(epoch, timezone);
    const local = new Date(epoch + offset * 60000);

    return `${local.getUTCFullYear()}-${pad(local.getUTCMonth() + 1)}-${pad(local.getUTCDate())}T${pad(local.getUTCHours())}:${pad(local.getUTCMinutes())}:00${formatOffset(offset)}`;
}

/** An RFC 3339 instant as the wall date and time it shows in `timezone`; a value without an offset is not an instant and gives blanks. */
export function fromInstant(
    value: string,
    timezone: string,
): { date: string; time: string } {
    if (!INSTANT.test(value)) return { date: '', time: '' };
    const epoch = Date.parse(value);
    if (Number.isNaN(epoch)) return { date: '', time: '' };
    const local = new Date(epoch + offsetAt(epoch, timezone) * 60000);

    return {
        date: `${local.getUTCFullYear()}-${pad(local.getUTCMonth() + 1)}-${pad(local.getUTCDate())}`,
        time: `${pad(local.getUTCHours())}:${pad(local.getUTCMinutes())}`,
    };
}

/** A wall time from a 24-hour `HH:MM`, or blank. Seconds are dropped. */
export function normalizeTime(value: string): string {
    return isTime(value) ? value.slice(0, 5) : '';
}

/** The year span a month or year picker offers by default: 100 years back and 20 ahead of `today`'s year. */
export function yearSpan(today: string, back = 100, ahead = 20): number[] {
    const year = Number(today.slice(0, 4));

    return Array.from({ length: back + ahead + 1 }, (_, i) => year + ahead - i);
}

/** Month labels for `locale`, January first, read as calendar months (UTC, never shifted). */
export function monthNames(
    locale: string | undefined,
    width: 'long' | 'short' = 'long',
): string[] {
    const format = new Intl.DateTimeFormat(locale, {
        month: width,
        timeZone: 'UTC',
    });

    return Array.from({ length: 12 }, (_, i) =>
        format.format(new Date(Date.UTC(2000, i, 1))),
    );
}

/**
 * Where a key moves focus in a month or year grid (`count` cells, `columns` wide), following the APG grid: arrows move one cell (Up and Down a row), Home and End
 * go to the row's ends, Control+Home and Control+End to the first and last cell, PageUp and PageDown to the same cell a page away. `null` means the key leaves the
 * grid alone. A result outside `0…count-1` is on the previous or next page: the picker turns the page and focuses the result modulo `count`.
 */
export function gridMove(
    index: number,
    key: string,
    columns: number,
    count: number,
    control = false,
): number | null {
    const row = Math.floor(index / columns) * columns;
    switch (key) {
        case 'ArrowLeft':
            return index - 1;
        case 'ArrowRight':
            return index + 1;
        case 'ArrowUp':
            return index - columns;
        case 'ArrowDown':
            return index + columns;
        case 'Home':
            return control ? 0 : row;
        case 'End':
            return control ? count - 1 : Math.min(row + columns, count) - 1;
        case 'PageUp':
            return index - count;
        case 'PageDown':
            return index + count;
        default:
            return null;
    }
}
