/*
 * Labels for a workspace's Email sending page: the From line as mail shows it, a live preview of the address a form would send from, what each DNS record
 * is for and its full name, and when the domain was last checked. Plain functions over strings and Intl, so a host's own page or `@kit/ui` can reuse them.
 */

/** A DNS record as the mail provider lists it: its type and its name, relative to the zone it goes in. */
export type MailDnsRecord = { type: string; name: string };

/** The From line as a mail client shows it: `Name <address>`. */
export function fromLine(name: string, address: string): string {
    return `${name} <${address}>`;
}

/**
 * The address a domain choice sends from, as the person types: `<local>@<domain>` for the domain itself (`root`), or `<local>@send.<domain>` for the send.
 * subdomain. Lowercased and trimmed; a typed `send.` is not doubled; an empty local part reads `hello` and an empty domain `example.com`, the page's placeholders.
 */
export function fromPreview(
    local: string,
    domain: string,
    root = false,
): string {
    const name =
        domain
            .trim()
            .toLowerCase()
            .replace(/^send\./, '') || 'example.com';

    return `${local.trim().toLowerCase() || 'hello'}@${root ? '' : 'send.'}${name}`;
}

/** What a sending domain's DNS record is for: DKIM, SPF, MX or the return path, read from its type and name; any other type is shown as itself. */
export function recordKind({ type, name }: MailDnsRecord): string {
    const kind = type.toUpperCase();
    if (name.includes('_domainkey')) return 'DKIM';
    if (kind === 'TXT') return 'SPF';
    if (kind === 'MX') return 'MX';
    if (kind === 'CNAME') return 'Return path';
    return type;
}

/**
 * A record's full name, for a DNS host that wants it whole. The provider names a record relative to the zone the domain is in, so the name's last labels
 * may repeat the domain's first ones (`resend._domainkey.send` for `send.example.com`): the overlap is joined once (`resend._domainkey.send.example.com`).
 * A name that is already whole is returned as it is; an empty name or `@` is the domain itself.
 */
export function fullRecordName(name: string, domain: string): string {
    const host = domain.trim().toLowerCase().replace(/\.$/, '');
    const own = name.trim().replace(/\.$/, '');
    if (own === '' || own === '@') return host;
    const labels = own.split('.');
    const zone = host.split('.');
    for (
        let overlap = Math.min(labels.length, zone.length);
        overlap > 0;
        overlap--
    ) {
        if (
            labels.slice(-overlap).join('.').toLowerCase() ===
            zone.slice(0, overlap).join('.')
        ) {
            return [...labels, ...zone.slice(overlap)].join('.');
        }
    }
    return `${own}.${host}`;
}

/**
 * When a domain was last checked, in the viewer's timezone: "Today 3:37 PM", "Yesterday 3:37 PM" or "Nov 3, 3:37 PM". `checkedAt` is the server's value: a
 * database timestamp without an offset (`2026-11-06 20:37:08`) is read as UTC; one with an offset or `Z` keeps it. A value that is not a time is shown as given.
 */
export function checkedAtLabel(
    checkedAt: string,
    timezone: string,
    now: Date = new Date(),
    locale?: string,
): string {
    const value = checkedAt.trim().replace(' ', 'T');
    const date = new Date(
        /(Z|[+-]\d\d:?\d\d)$/i.test(value) ? value : `${value}Z`,
    );
    if (
        !/^\d{4}-\d\d-\d\dT\d\d:\d\d/.test(value) ||
        Number.isNaN(date.getTime())
    )
        return checkedAt;
    const day = (at: Date): string =>
        new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(at);
    const clock = new Intl.DateTimeFormat(locale, {
        timeZone: timezone,
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
    const today = day(now);
    if (day(date) === today) return `Today ${clock}`;
    // The calendar day before today, from its date (not 24 hours back, which a 23-hour DST day would skip past).
    const [year, month, dayOfMonth] = today.split('-').map(Number);
    const yesterday = new Date(Date.UTC(year, month - 1, dayOfMonth - 1))
        .toISOString()
        .slice(0, 10);
    if (day(date) === yesterday) return `Yesterday ${clock}`;
    return `${new Intl.DateTimeFormat(locale, { timeZone: timezone, month: 'short', day: 'numeric' }).format(date)}, ${clock}`;
}
