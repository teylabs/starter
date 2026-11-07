import { router } from '@inertiajs/vue3';
import { getCurrentInstance, ref } from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import { confirmationData } from '@/components/Kit/Overlay/confirmText';

/*
 * How ActionBar and HeaderActions run a descriptor. A host that listens to `action` runs it itself; otherwise its `confirm` comes first (typed when it has
 * `typed`, sent as `confirmation` for the server to re-check), then an Inertia visit. Call it from setup, with the component's `action` emit.
 */
export function useActionRunner(
    emit: (event: 'action', action: ActionDescriptor) => void,
) {
    const instance = getCurrentInstance();
    const hostRuns = () => Boolean(instance?.vnode.props?.onAction);
    // Kept apart from `asking`: the dialog closes itself around the click that confirms it.
    const pending = ref<ActionDescriptor | null>(null);
    const asking = ref(false);
    // The action whose visit is in flight: its button spins, and every action waits until it finishes, so a second click never fires it twice.
    const running = ref<ActionDescriptor | null>(null);
    function visit(action: ActionDescriptor, confirmation?: string): void {
        if (running.value !== null) return;
        router.visit(action.url, {
            method: action.method as
                'get' | 'post' | 'put' | 'patch' | 'delete',
            data: confirmationData(confirmation),
            preserveScroll: true,
            onStart: () => {
                running.value = action;
            },
            onFinish: () => {
                running.value = null;
            },
        });
    }
    const isRunning = (action: ActionDescriptor) =>
        running.value !== null &&
        (running.value === action ||
            (running.value.key ?? running.value.url) ===
                (action.key ?? action.url));
    function run(action: ActionDescriptor): void {
        if (hostRuns()) {
            emit('action', action);
            return;
        }
        if (action.confirm) {
            pending.value = action;
            // After a menu has closed and given focus back: opened in the same tick, the menu's own dismissal swallows the confirmation.
            setTimeout(() => (asking.value = true), 0);
            return;
        }
        visit(action);
    }

    return { hostRuns, pending, asking, run, visit, running, isRunning };
}
