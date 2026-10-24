export type NumberKind = 'money' | 'decimal' | 'integer';
export type NumberOptions = { kind: NumberKind; locale?: string; currency?: string; scale?: number; signed?: boolean; min?: string; max?: string; step?: string; grouping?: boolean };
export function numberFormat(options: NumberOptions) {
    const locale = options.locale ?? 'en-CA';
    const formatter = new Intl.NumberFormat(locale, options.kind === 'money' ? { style: 'currency', currency: options.currency ?? 'CAD' } : {});
    const scale = options.kind === 'integer' ? 0 : options.scale ?? (options.kind === 'money' ? formatter.resolvedOptions().maximumFractionDigits ?? 2 : 2);
    if (!/^\d+$/.test(String(scale)) || scale < 0 || scale > 20) throw new Error('Scale must be between 0 and 20.');
    const parts = new Intl.NumberFormat(locale).formatToParts(12345n);
    const group = parts.find(p => p.type === 'group')?.value ?? ',';
    const decimal = new Intl.NumberFormat(locale, { minimumFractionDigits: 1 }).formatToParts(0n).find(p => p.type === 'decimal')?.value ?? '.';
    const currency = formatter.formatToParts(0n).find(p => p.type === 'currency')?.value ?? '';
    const canonicalUnits = (value: string): bigint => {
        if (options.kind !== 'decimal') {
            if (!/^-?\d+$/.test(value)) throw new Error('Invalid canonical integer.');
            return BigInt(value);
        }
        if (!new RegExp(`^-?\\d+\\.\\d{${scale}}$`).test(value) && !(scale === 0 && /^-?\d+$/.test(value))) throw new Error('Invalid canonical decimal.');
        return BigInt(value.replace('.', ''));
    };
    const canonical = (units: bigint): string => {
        const sign = units < 0n ? '-' : '';
        const digits = (units < 0n ? -units : units).toString().padStart(scale + 1, '0');
        return options.kind === 'decimal' && scale ? `${sign}${digits.slice(0, -scale)}.${digits.slice(-scale)}` : units.toString();
    };
    return {
        scale,
        parse(text: string): { value: string; error: string } {
            if (!text.trim()) return { value: '', error: '' };
            let raw = text.trim().replace(/(?:CA|US|AU|NZ|HK|SG)\$/g, '');
            if (currency) raw = raw.split(currency).join('');
            if (options.currency) raw = raw.split(options.currency).join('');
            raw = raw.replace(/[$€£¥\s\u00a0\u202f]/g, '').replace(/−/g, '-');
            if (/^\(.*\)$/.test(raw)) raw = `-${raw.slice(1, -1)}`;
            raw = raw.split(group).join('').split(decimal).join('.');
            if (!/^-?(?:\d+(?:\.\d*)?|\.\d+)$/.test(raw)) return { value: '', error: 'Enter a valid number.' };
            if (raw.startsWith('-') && !options.signed) return { value: '', error: 'Enter a positive number.' };
            const negative = raw.startsWith('-');
            const [whole, fraction = ''] = raw.replace(/^-/, '').split('.');
            if (fraction.length > scale) return { value: '', error: `Use at most ${scale} decimal places.` };
            const units = BigInt((whole || '0') + fraction.padEnd(scale, '0')) * (negative ? -1n : 1n);
            return { value: canonical(units), error: '' };
        },
        validate(value: string): string {
            if (!value) return '';
            const units = canonicalUnits(value);
            if (options.min !== undefined && units < canonicalUnits(options.min)) return `Minimum: ${options.min}.`;
            if (options.max !== undefined && units > canonicalUnits(options.max)) return `Maximum: ${options.max}.`;
            if (options.step !== undefined) {
                const step = canonicalUnits(options.step);
                if (step <= 0n) throw new Error('Step must be positive.');
                if ((units - canonicalUnits(options.min ?? (options.kind === 'decimal' && scale ? `0.${'0'.repeat(scale)}` : '0'))) % step !== 0n) return `Use increments of ${options.step}.`;
            }
            return '';
        },
        format(value: string): string {
            if (!value) return '';
            const units = canonicalUnits(value);
            const sign = units < 0n ? '-' : '';
            const digits = (units < 0n ? -units : units).toString().padStart(scale + 1, '0');
            const whole = scale ? digits.slice(0, -scale) : digits;
            const grouped = new Intl.NumberFormat(locale, { maximumFractionDigits: 0, useGrouping: options.grouping ?? true }).format(BigInt(whole));
            return `${sign}${grouped}${scale ? decimal + digits.slice(-scale) : ''}`;
        },
    };
}
