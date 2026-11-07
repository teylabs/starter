import { visitModal } from '@inertiaui/modal-vue';
import { readonly, ref } from 'vue';

type ModalVisitOptions = NonNullable<Parameters<typeof visitModal>[1]>;

/*
 * A modal opened from code (docs/pending-actions.md): `open()` runs visitModal with a loading flag for the button that asked, and ignores a second open while the
 * first is in flight, so a slow modal visit never stacks two dialogs. Resolves to the modal, or to null for an ignored open. A failed visit clears the flag.
 */
export function useModalVisit() {
    const loading = ref(false);
    async function open(url: string, options: ModalVisitOptions = {}) {
        if (loading.value) return null;
        loading.value = true;
        try {
            return await visitModal(url, options);
        } finally {
            loading.value = false;
        }
    }

    return { loading: readonly(loading), open };
}
