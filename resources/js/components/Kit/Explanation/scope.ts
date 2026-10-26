import type { ComputedRef, InjectionKey, Ref } from 'vue';
export const overlayKey: InjectionKey<boolean> = Symbol('kit-overlay');
export type WorkingMember = { open: ComputedRef<boolean>; request: (value: boolean, batch?: boolean) => void; containsFocus: () => boolean };
export type WorkingScope = { members: Map<symbol, WorkingMember>; intent: Ref<boolean>; version: Ref<number>; register: (key: symbol, member: WorkingMember) => void; unregister: (key: symbol) => void; request: (value: boolean, toggle?: HTMLElement) => void; all: ComputedRef<boolean>; some: ComputedRef<boolean>; status: Ref<string> };
export const scopeKey: InjectionKey<WorkingScope> = Symbol('computed-value-scope');
