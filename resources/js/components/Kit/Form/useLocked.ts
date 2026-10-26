import { computed, inject, onBeforeUnmount, provide, reactive, readonly, ref, type ComputedRef, type InjectionKey, type Ref } from 'vue';
import { NOTICES, resolveLocked, type LockedProps, type LockedState } from './locked';

// The locked context (the LockedForm spec): LockedForm provides it, every kit control and kit edit action reads it with `useLocked()`, and callers rarely need
// it directly. The notice is claimed once per area: the first FormCard (or a lone FormFieldset) renders it with the id the area's root describes itself by.
type LockedContext = { state: ComputedRef<LockedState>; noticeId: string; claimed: Ref<boolean> };
const key: InjectionKey<LockedContext> = Symbol('kit.locked');
// Whether a FormCard sits between this component and the area: the card then owns the notice, so a fieldset inside it renders none.
const cardKey: InjectionKey<boolean> = Symbol('kit.locked.card');

/** Provides the area's state to its subtree, locked when its `save` is null or any ancestor area is locked. */
export function provideLocked(props: () => LockedProps, noticeId: string): LockedContext {
    const parent = inject(key, null);
    const context: LockedContext = { state: computed(() => resolveLocked(props(), parent?.state.value)), noticeId, claimed: ref(false) };
    provide(key, context);
    provide(cardKey, false);

    return context;
}

/** The nearest area's `{ locked, notice, reason }`, reactive and read-only; outside any LockedForm it is unlocked with the default plural notice. */
export function useLocked(): Readonly<LockedState> {
    return readonly(reactive(stateOf(inject(key, null))));
}
function stateOf(context: LockedContext | null) {
    return {
        locked: computed(() => context?.state.value.locked ?? false),
        notice: computed(() => context?.state.value.notice ?? NOTICES.many),
        reason: computed(() => context?.state.value.reason ?? null),
    };
}

/** A FormCard marks itself as the notice's owner for the fieldsets inside it. */
export function provideNoticeOwner(): void {
    provide(cardKey, true);
}

/** Whether a FormCard owns the notice above this component (a lone fieldset in its own area renders the notice itself). */
export function useNoticeOwned(): boolean {
    return inject(cardKey, false);
}

/** The notice this component renders when the area is locked: the state, and the id the first claimant carries (the area's root describes itself by it). */
export function useLockedNotice(claim = true): Readonly<LockedState & { id: string | undefined }> {
    const context = inject(key, null);
    const holder = ref(false);
    if (claim && context && !context.claimed.value) {
        context.claimed.value = true;
        holder.value = true;
        onBeforeUnmount(() => { context.claimed.value = false; holder.value = false; });
    }

    return readonly(reactive({ ...stateOf(context), id: computed(() => (holder.value ? context?.noticeId : undefined)) }));
}
