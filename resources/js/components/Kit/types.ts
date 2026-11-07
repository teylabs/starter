/*
 * The few Inertia types the kit's components need, defined here so no stub imports @inertiajs/core: the host declares only @inertiajs/vue3, which pins its
 * own @inertiajs/core, and a second declared copy breaks PageProps typing. Each matches Inertia's own shape for what the kit uses.
 */

/** An HTTP method, as Inertia's requests take it. */
export type Method = 'get' | 'post' | 'put' | 'patch' | 'delete';

/** The part of Inertia's InfiniteScroll component instance the kit calls: load the next page with these request callbacks. */
export interface InfiniteScrollRef {
    fetchNext(options?: Record<string, unknown>): void;
}
