import { computed, onBeforeUnmount, onMounted, type ComputedRef } from 'vue';
import { usePage, usePoll } from '@inertiajs/vue3';

/**
 * True when the server sent `prop` as an Inertia live prop on this page. A component that reloads the prop itself (a hand-wired
 * realtime reader, say) stands down while this is true, so one broadcast is one partial reload, not two.
 */
export function useIsLive(prop: string): ComputedRef<boolean> {
    const page = usePage();

    return computed(() => {
        const live = (
            page as unknown as { liveProps?: Record<string, unknown> | null }
        ).liveProps;

        return live != null && Object.prototype.hasOwnProperty.call(live, prop);
    });
}

export interface LiveFallbackPollOptions {
    /** Whether a socket is configured: the poll is slow with one and fast without. */
    socket: boolean;
    /** Milliseconds between polls with a socket. */
    slow?: number;
    /** Milliseconds between polls without one. */
    fast?: number;
}

/**
 * The fallback for live props: a partial-reload poll of `only`, slow when a socket is configured (in case it's down) and fast when
 * not, and only while the tab is visible. Inertia throttles hidden tabs rather than pausing them, so visibility starts and stops it.
 */
export function useLiveFallbackPoll(
    only: readonly string[],
    options: LiveFallbackPollOptions,
): void {
    const { start, stop } = usePoll(
        options.socket ? (options.slow ?? 30000) : (options.fast ?? 5000),
        { only: [...only] },
        { keepAlive: false, autoStart: false },
    );
    const follow = () =>
        document.visibilityState === 'visible' ? start() : stop();

    onMounted(() => {
        follow();
        document.addEventListener('visibilitychange', follow);
    });
    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', follow);
        stop();
    });
}
