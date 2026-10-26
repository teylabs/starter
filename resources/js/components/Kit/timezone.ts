// The browser's timezone, for the server. The kit keeps it in the `tz` cookie (a functional cookie, not tracking): it is set once, early, and rewritten only when the
// zone changes, so every request (a full load, an Inertia visit, a form, a download) carries it. The server validates it against PHP's own list and never trusts it.
export function browserTimezone(): string | null {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
    } catch {
        return null;
    }
}

export function syncTimezoneCookie(): void {
    if (typeof document === 'undefined') return;
    const zone = browserTimezone();
    if (!zone) return;
    const current = document.cookie.split('; ').find((part) => part.startsWith('tz='))?.slice(3);
    if (current !== undefined && decodeURIComponent(current) === zone) return;
    const secure = typeof location !== 'undefined' && location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `tz=${encodeURIComponent(zone)}; path=/; max-age=31536000; SameSite=Lax${secure}`;
}
