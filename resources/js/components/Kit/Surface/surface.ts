import { computed, inject, provide, type ComputedRef, type InjectionKey } from 'vue';

// The surface a control sits on: its tone and its shape. A container with its own tone (the FloatingToolbar's pill) provides `inverse`, and a pill container
// provides the `pill` shape; Button and ActionBar's groups read both and blend in instead of each caller passing a one-off variant. Elsewhere the defaults hold.
export type Surface = { tone: 'default' | 'inverse'; shape: 'default' | 'pill' };
const key: InjectionKey<ComputedRef<Surface>> = Symbol('kit.surface');

export function provideSurface(surface: () => Surface): void {
    provide(key, computed(surface));
}

export function useSurface(): ComputedRef<Surface> {
    return inject(key, computed((): Surface => ({ tone: 'default', shape: 'default' })));
}

// A control on a pill surface has a group segment's box: 36px tall, 14px side padding, no vertical padding, full radius, 14px medium text.
export const pillControl = 'rounded-full! h-9! px-3.5! py-0! text-sm! font-medium!';
// A primary button or filled segment on an inverse surface, with hairline dividers between segments.
export const inverseFilled = 'bg-primary text-primary-foreground hover:bg-primary/90 dark:bg-primary dark:hover:bg-primary/90';
// Every other control on an inverse surface: transparent, inherited text, a current-colour hairline and wash.
export const inverseControl = 'bg-transparent text-current ring-1 ring-current/20 ring-inset hover:bg-current/10 dark:bg-transparent dark:hover:bg-current/10';
