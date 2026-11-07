export type NumberKind = 'money' | 'percent' | 'decimal' | 'integer';
/**
 * `percent` is canonical in percentage points at a fixed `scale` (default 2): "12.5" is 12.5 %, never a fraction, so "0.125" stays 0.125 %. `money` is canonical in
 * integer minor units of `currency`; without a currency it has no symbol and two decimals (the components warn in development rather than guess one). `negative`
 * chooses how `format` shows a negative value: a leading minus (default) or accounting parentheses.
 */
export type NumberOptions = {
    kind: NumberKind;
    locale?: string;
    currency?: string;
    scale?: number;
    signed?: boolean;
    min?: string;
    max?: string;
    step?: string;
    grouping?: boolean;
    negative?: 'minus' | 'parentheses';
};
export type NumberDefaults = { locale: string; currency?: string };
// The host's defaults for money and percent: `kitNumber` (config kit.currency and the app locale, shared by the kit) wins, then the browser's language, then en-CA.
export function numberDefaults(
    shared?: { locale?: string | null; currency?: string | null } | null,
    browser?: string | null,
): NumberDefaults {
    const known = (locale?: string | null) => {
        try {
            return locale && Intl.NumberFormat.supportedLocalesOf(locale).length
                ? locale
                : undefined;
        } catch {
            return undefined;
        }
    };
    return {
        locale: known(shared?.locale) ?? known(browser) ?? 'en-CA',
        currency: shared?.currency || undefined,
    };
}
export function numberFormat(options: NumberOptions) {
    const locale = options.locale ?? 'en-CA';
    const money =
        options.kind === 'money' && options.currency
            ? new Intl.NumberFormat(locale, {
                  style: 'currency',
                  currency: options.currency,
              })
            : null;
    const decimalLike =
        options.kind === 'decimal' || options.kind === 'percent';
    const scale =
        options.kind === 'integer'
            ? 0
            : (options.scale ??
              (options.kind === 'money'
                  ? (money?.resolvedOptions().maximumFractionDigits ?? 2)
                  : 2));
    if (!/^\d+$/.test(String(scale)) || scale < 0 || scale > 20)
        throw new Error('Scale must be between 0 and 20.');
    const parts = new Intl.NumberFormat(locale).formatToParts(12345n);
    const group = parts.find((p) => p.type === 'group')?.value ?? ',';
    const decimal =
        new Intl.NumberFormat(locale, { minimumFractionDigits: 1 })
            .formatToParts(0n)
            .find((p) => p.type === 'decimal')?.value ?? '.';
    const currency =
        money?.formatToParts(0n).find((p) => p.type === 'currency')?.value ??
        '';
    // The symbol around the number, in the locale's order and spacing: "$" before in en-CA, " $" after in fr-CA, " %" after in fr-CA.
    const affixParts =
        money?.formatToParts(1) ??
        (options.kind === 'percent'
            ? new Intl.NumberFormat(locale, { style: 'percent' }).formatToParts(
                  0.01,
              )
            : []);
    const first = affixParts.findIndex((p) => p.type === 'integer');
    let last = -1;
    affixParts.forEach((p, index) => {
        if (['integer', 'decimal', 'fraction'].includes(p.type)) last = index;
    });
    const affix =
        first < 0
            ? { prefix: '', suffix: '' }
            : {
                  prefix: affixParts
                      .slice(0, first)
                      .map((p) => p.value)
                      .join(''),
                  suffix: affixParts
                      .slice(last + 1)
                      .map((p) => p.value)
                      .join(''),
              };
    const canonicalUnits = (value: string): bigint => {
        if (!decimalLike) {
            if (!/^-?\d+$/.test(value))
                throw new Error('Invalid canonical integer.');
            return BigInt(value);
        }
        if (
            !new RegExp(`^-?\\d+\\.\\d{${scale}}$`).test(value) &&
            !(scale === 0 && /^-?\d+$/.test(value))
        )
            throw new Error('Invalid canonical decimal.');
        return BigInt(value.replace('.', ''));
    };
    const canonical = (units: bigint): string => {
        const sign = units < 0n ? '-' : '';
        const digits = (units < 0n ? -units : units)
            .toString()
            .padStart(scale + 1, '0');
        return decimalLike && scale
            ? `${sign}${digits.slice(0, -scale)}.${digits.slice(-scale)}`
            : units.toString();
    };
    return {
        scale,
        affix,
        parse(text: string): { value: string; error: string } {
            if (!text.trim()) return { value: '', error: '' };
            let raw = text.trim().replace(/(?:CA|US|AU|NZ|HK|SG)\$/g, '');
            if (currency) raw = raw.split(currency).join('');
            if (options.currency) raw = raw.split(options.currency).join('');
            if (options.kind === 'percent') raw = raw.replace(/[%\u066a]/g, '');
            raw = raw.replace(/[$€£¥\s\u00a0\u202f]/g, '').replace(/−/g, '-');
            if (/^\(.*\)$/.test(raw)) raw = `-${raw.slice(1, -1)}`;
            raw = raw.split(group).join('').split(decimal).join('.');
            if (!/^-?(?:\d+(?:\.\d*)?|\.\d+)$/.test(raw))
                return { value: '', error: 'Enter a valid number.' };
            if (raw.startsWith('-') && !options.signed)
                return { value: '', error: 'Enter a positive number.' };
            const negative = raw.startsWith('-');
            const [whole, fraction = ''] = raw.replace(/^-/, '').split('.');
            if (fraction.length > scale)
                return {
                    value: '',
                    error: `Use at most ${scale} decimal places.`,
                };
            const units =
                BigInt((whole || '0') + fraction.padEnd(scale, '0')) *
                (negative ? -1n : 1n);
            return { value: canonical(units), error: '' };
        },
        validate(value: string): string {
            if (!value) return '';
            const units = canonicalUnits(value);
            if (
                options.min !== undefined &&
                units < canonicalUnits(options.min)
            )
                return `Minimum: ${options.min}.`;
            if (
                options.max !== undefined &&
                units > canonicalUnits(options.max)
            )
                return `Maximum: ${options.max}.`;
            if (options.step !== undefined) {
                const step = canonicalUnits(options.step);
                if (step <= 0n) throw new Error('Step must be positive.');
                if (
                    (units -
                        canonicalUnits(
                            options.min ??
                                (decimalLike && scale
                                    ? `0.${'0'.repeat(scale)}`
                                    : '0'),
                        )) %
                        step !==
                    0n
                )
                    return `Use increments of ${options.step}.`;
            }
            return '';
        },
        format(value: string): string {
            if (!value) return '';
            const units = canonicalUnits(value);
            const sign = units < 0n ? '-' : '';
            const digits = (units < 0n ? -units : units)
                .toString()
                .padStart(scale + 1, '0');
            const whole = scale ? digits.slice(0, -scale) : digits;
            const grouped = new Intl.NumberFormat(locale, {
                maximumFractionDigits: 0,
                useGrouping: options.grouping ?? true,
            }).format(BigInt(whole));
            const number = `${grouped}${scale ? decimal + digits.slice(-scale) : ''}`;
            return sign && options.negative === 'parentheses'
                ? `(${number})`
                : `${sign}${number}`;
        },
    };
}
