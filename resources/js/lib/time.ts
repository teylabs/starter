// The app stores and sends instants in UTC (app.timezone is UTC). Show them in the viewer's own zone, which only
// the browser knows; pass `zone` to show another.
export interface LocalTimeOptions extends Intl.DateTimeFormatOptions {
    zone?: string;
    locale?: string;
}

const instant = /(Z|[+-]\d{2}:?\d{2})$/i;

export function localTime(utc: string, { zone, locale, ...format }: LocalTimeOptions = {}): string {
    // A timestamp without an offset would be read in the viewer's zone instead of as UTC.
    const date = instant.test(utc.trim()) ? new Date(utc) : new Date(Number.NaN);
    if (Number.isNaN(date.getTime())) throw new RangeError(`Not a UTC instant: ${utc}`);
    const options = Object.keys(format).length ? format : { dateStyle: 'medium', timeStyle: 'short' } as const;

    return new Intl.DateTimeFormat(locale, { ...options, timeZone: zone }).format(date);
}
