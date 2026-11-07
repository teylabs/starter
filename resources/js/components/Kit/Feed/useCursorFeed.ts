import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { createCursorFeed, type CursorFeedPage } from './activity';

export type CursorFeedConfig = {
    /** The page prop holding `{ entries, <cursorKey>, <syncKey> }`. */
    prop: string;
    cursorKey?: string;
    syncKey?: string;
    /** The query parameter the server reads the cursor from. */
    param?: string;
};

/**
 * A cursor-paged feed over one Inertia page prop: `loadMore()` makes a partial reload of only that prop with the cursor, and the pages accumulate (the rules are
 * createCursorFeed's). The URL is kept, so a reload or a shared link starts at the head. Pass `entries`, `more` and `loading` to ActivityStream and call
 * `loadMore` from its `load-more` event.
 */
export function useCursorFeed<T extends { id: string }>(
    config: CursorFeedConfig,
) {
    const {
        prop,
        cursorKey = 'next_cursor',
        syncKey = 'sync_token',
        param = 'cursor',
    } = config;
    const page = usePage();
    const current = () =>
        (page.props[prop] as CursorFeedPage<T> | undefined) ?? { entries: [] };
    const feed = createCursorFeed<T>({
        first: current(),
        cursorKey,
        syncKey,
        wrap: <S extends object>(value: S) => reactive(value) as S,
        fetch: (cursor) =>
            router.reload({
                only: [prop],
                data: { [param]: cursor },
                preserveUrl: true,
                onError: () => feed.failed(),
                onHttpException: () => {
                    feed.failed();
                    return false;
                },
                onNetworkError: () => {
                    feed.failed();
                    return false;
                },
            }),
    });
    const state = feed.state;
    watch(
        () => page.props[prop],
        (next) => {
            if (next) feed.receive(next as CursorFeedPage<T>);
        },
    );

    return {
        entries: computed(() => state.entries as T[]),
        more: computed(() => state.cursor !== null),
        loading: computed(() => state.loading),
        /** True once the history was rewritten while the viewer read it and the feed went back to the head page. */
        resynced: computed(() => state.resynced),
        loadMore: () => feed.loadMore(),
    };
}
