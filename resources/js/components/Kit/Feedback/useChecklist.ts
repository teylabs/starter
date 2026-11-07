import {
    computed,
    type ComputedRef,
    toValue,
    type MaybeRefOrGetter,
} from 'vue';
import {
    checklistProgress,
    type ChecklistItem,
    type ChecklistProgress,
} from './checklist';

/** The reactive form: pass the items (a ref, a getter or a value) and read progress and the announcement from it. */
export function useChecklist(
    items: MaybeRefOrGetter<readonly ChecklistItem[] | null | undefined>,
): {
    items: ComputedRef<readonly ChecklistItem[]>;
    progress: ComputedRef<ChecklistProgress>;
} {
    const list = computed(() => toValue(items) ?? []);
    return {
        items: list,
        progress: computed(() => checklistProgress(list.value)),
    };
}
