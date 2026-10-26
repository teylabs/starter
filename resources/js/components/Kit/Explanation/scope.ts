import type { ComputedRef, InjectionKey, Ref } from 'vue';
import { sheetKey } from '@/components/Kit/Overlay/popover';
/** @deprecated Since 0.4; use `sheetKey` from `@/components/Kit/Overlay/popover`. Removed in 0.5. */
export const overlayKey: InjectionKey<boolean> = sheetKey;
export type WorkingMember = { open: ComputedRef<boolean>; request: (value: boolean, batch?: boolean) => void; containsFocus: () => boolean };
export type WorkingScope = { members: Map<symbol, WorkingMember>; intent: Ref<boolean>; version: Ref<number>; register: (key: symbol, member: WorkingMember) => void; unregister: (key: symbol) => void; request: (value: boolean, toggle?: HTMLElement) => void; all: ComputedRef<boolean>; some: ComputedRef<boolean>; status: Ref<string> };
export const scopeKey: InjectionKey<WorkingScope> = Symbol('computed-value-scope');
