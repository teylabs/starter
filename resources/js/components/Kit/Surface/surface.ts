import {
    computed,
    inject,
    provide,
    type ComputedRef,
    type InjectionKey,
} from 'vue';

// The surface a control sits on: its tone and its shape. A container with its own tone (the FloatingToolbar's pill) provides `inverse`, and a pill container
// provides the `pill` shape; Button and ActionBar's groups read both and blend in instead of each caller passing a one-off variant. Elsewhere the defaults hold.
export type Surface = {
    tone: 'default' | 'inverse';
    shape: 'default' | 'pill';
};
const key: InjectionKey<ComputedRef<Surface>> = Symbol('kit.surface');

export function provideSurface(surface: () => Surface): void {
    provide(key, computed(surface));
}

export function useSurface(): ComputedRef<Surface> {
    return inject(
        key,
        computed((): Surface => ({ tone: 'default', shape: 'default' })),
    );
}

// A control on a pill surface has a group segment's box: 36px tall, 14px side padding, no vertical padding, full radius, 14px medium text.
export const pillControl =
    'rounded-full! h-9! px-3.5! py-0! text-sm! font-medium!';
// A primary button or filled segment on an inverse surface, with hairline dividers between segments.
export const inverseFilled =
    'bg-primary text-primary-foreground hover:bg-primary/90 dark:bg-primary dark:hover:bg-primary/90';
// Every other control on an inverse surface: transparent, inherited text, a current-colour hairline and wash.
export const inverseControl =
    'bg-transparent text-current ring-1 ring-current/20 ring-inset hover:bg-current/10 dark:bg-transparent dark:hover:bg-current/10';

// The page ground (README, "Tinted page grounds"). A theme whose ground is tinted rather than white declares it with one token in its own CSS,
// `--kit-ground: tinted` (on :root), and the kit's content components then draw their own card on it: DataTable, EmptyState, Workbench and PageSection (the
// form layouts, FormCard and Section, are cards already). Read from the root's computed style when a component is set up; no declaration, or no document, is
// the white ground, where nothing changes.
export function tintedGround(): boolean {
    if (typeof document === 'undefined') return false;
    return (
        getComputedStyle(document.documentElement)
            .getPropertyValue('--kit-ground')
            .trim() === 'tinted'
    );
}

// Whether what is set up here already sits on a card: a Surface or Card, a FormCard or Section, a dashboard widget, a Dialog or Modal, or a component that drew
// its ground card. A card is never drawn inside another one.
const cardKey: InjectionKey<ComputedRef<boolean>> = Symbol('kit.card');

export function provideCard(on: () => boolean = () => true): void {
    const outer = inject(
        cardKey,
        computed(() => false),
    );
    provide(
        cardKey,
        computed(() => outer.value || on()),
    );
}

export function useInCard(): ComputedRef<boolean> {
    return inject(
        cardKey,
        computed(() => false),
    );
}

// The card the ground asks a component to draw: on a tinted ground, when `enabled` (a PageSection's `surface`), and never inside a card. What is inside then
// sits on a card. The card's look matches a dashboard widget's: `bg-card text-card-foreground rounded-xl border`.
export const groundCard = 'bg-card text-card-foreground rounded-xl border';

export function useGroundSurface(
    enabled: () => boolean = () => true,
): ComputedRef<boolean> {
    const tinted = tintedGround();
    const inCard = useInCard();
    const drawn = computed(() => tinted && enabled() && !inCard.value);
    provideCard(() => drawn.value);
    return drawn;
}
