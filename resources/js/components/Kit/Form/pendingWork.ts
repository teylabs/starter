import { computed, readonly, ref, type ComputedRef, type Ref } from 'vue';

/*
 * Pending work: saves the form does not see yet (an upload in flight, a draft still being written, an autosave). While any is registered, the leave-page guard
 * (formFrame.ts) treats the page as unsaved, even when the form itself is clean. BulkDrop's own uploads register themselves; a host registers its own with
 * `const work = usePendingWork()`, then `await work.track(promise)` or `const end = work.begin()` … `end()`. `end` is idempotent, so a `finally` that calls it
 * twice cannot drop another task's count. One registry per page: usePendingWork() always returns the same one. createPendingWork() makes a separate one.
 */
export interface PendingWork {
    /** How many tasks are in flight. */
    readonly count: Readonly<Ref<number>>;
    /** Whether any task is in flight. */
    readonly pending: ComputedRef<boolean>;
    /** Registers one task; call the returned function when it ends. */
    begin(): () => void;
    /** Registers `work` until it settles, and resolves or rejects as it does. */
    track<T>(work: PromiseLike<T> | T): Promise<T>;
}

export function createPendingWork(): PendingWork {
    const count = ref(0);
    function begin() {
        let open = true;
        count.value++;
        return () => {
            if (!open) return;
            open = false;
            count.value--;
        };
    }
    async function track<T>(work: PromiseLike<T> | T): Promise<T> {
        const end = begin();
        try {
            return await work;
        } finally {
            end();
        }
    }
    return {
        count: readonly(count),
        pending: computed(() => count.value > 0),
        begin,
        track,
    };
}

let page: PendingWork | undefined;

/** The page's pending-work registry, the one the leave-page guard reads. */
export function usePendingWork(): PendingWork {
    return (page ??= createPendingWork());
}
