import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

// The page each of the viewer's workspaces opens on. The shell sends only the list (`switcher.choices`: id, name, slug); this loads the rest from the
// server's `switcher.landings` when the switcher opens, or on hover or focus, once per page, for the switcher, the drawer and the workspace list alike.
// Each is decided on the server inside its own workspace. A workspace is `undefined` until it arrives (loading), and `null` when the viewer may open
// nothing there or the load failed (unavailable; the next open asks again): never a link the server did not send.
type Landing = { id: number; url: string | null };

const urls = ref(new Map<number, string | null>());
const status = ref<'idle' | 'loading' | 'loaded' | 'failed'>('idle');
let visit = 0;
let listening = false;

export function loadWorkspaceLandings(url: string | null | undefined): void {
    if (!url || status.value === 'loading' || status.value === 'loaded') return;
    // Each visit asks again (what the viewer may open can change between pages): from its start, as `navigate` also fires for the page already shown.
    if (!listening) {
        listening = true;
        router.on('start', () => {
            visit++;
            urls.value = new Map();
            status.value = 'idle';
        });
    }
    const asked = visit;
    status.value = 'loading';
    fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then((response) => (response.ok ? (response.json() as Promise<{ workspaces: Landing[] }>) : Promise.reject(new Error(String(response.status)))))
        .then((body) => {
            if (asked !== visit) return;
            urls.value = new Map(body.workspaces.map((landing) => [landing.id, landing.url]));
            status.value = 'loaded';
        })
        .catch(() => {
            if (asked === visit) status.value = 'failed';
        });
}

/** A workspace's page: its URL, `null` when unavailable, `undefined` while it is loading (or not asked for yet). */
export function workspaceLanding(id: number): string | null | undefined {
    if (status.value === 'loaded') return urls.value.get(id) ?? null;

    return status.value === 'failed' ? null : undefined;
}
