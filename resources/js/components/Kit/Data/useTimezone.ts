import { usePage } from '@inertiajs/vue3';
import {
    computed,
    onBeforeUnmount,
    ref,
    type ComputedRef,
    type Ref,
} from 'vue';
import { browserTimezone } from '../timezone';

/*
 * The viewer's timezone for timed values: the server's `kitTimezone` share (session-ui resolves it from the browser's `tz` cookie, the person's saved zone and
 * the workspace's), else the browser's own zone, else UTC. Calendar dates never use it. The locale: the page's `lang`, else the browser's.
 */
export function useTimezone(): ComputedRef<string> {
    let page: { props: Record<string, unknown> } | null = null;
    try {
        page = usePage();
    } catch {
        page = null;
    }
    return computed(() => {
        const shared = page?.props.kitTimezone;
        return typeof shared === 'string' && shared !== ''
            ? shared
            : (browserTimezone() ?? 'UTC');
    });
}

export function viewerLocale(): string {
    if (typeof document !== 'undefined' && document.documentElement.lang)
        return document.documentElement.lang;
    return typeof navigator !== 'undefined' && navigator.language
        ? navigator.language
        : 'en';
}

// One minute ticker for every live relative time on the page, running only while one is mounted.
const tick = ref(Date.now());
let subscribers = 0;
let timer: ReturnType<typeof setInterval> | null = null;

/** The current time, updated each minute while the caller is mounted. */
export function useMinuteTicker(): Ref<number> {
    subscribers++;
    if (timer === null)
        timer = setInterval(() => (tick.value = Date.now()), 60_000);
    onBeforeUnmount(() => {
        subscribers--;
        if (subscribers === 0 && timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    });
    return tick;
}
