import { inject, provide, type InjectionKey, type Ref } from 'vue';
import type { AnchorRect } from '@/components/Kit/Layout/toolbarAnchor';

/*
 * What a component inside a Workbench stage can reach of it: the workbench key (for useWorkbench and its `elements`), the stage's scrolling viewport (the
 * positioning box a stage-anchored FloatingToolbar sits in) and the stage toolbar's element (where a docked one renders). WorkbenchStage provides it; outside a
 * stage it is null. A selected stage element is found by its element attribute inside the viewport: `data-workbench-element="<id>"` by default, or the attribute the
 * host names (`<Workbench element-attribute="data-block-id">`, or `useWorkbench(key, { elementSelector })`), with the id it passes to selectElement(s).
 */
export type WorkbenchStageContext = {
    workbenchKey: string;
    viewport: Ref<HTMLElement | undefined>;
    toolbar: Ref<HTMLElement | undefined>;
};
const key: InjectionKey<WorkbenchStageContext> = Symbol('kit.workbench-stage');

export function provideWorkbenchStage(context: WorkbenchStageContext): void {
    provide(key, context);
}

export function useWorkbenchStage(): WorkbenchStageContext | null {
    return inject(key, null);
}

export const DEFAULT_ELEMENT_ATTRIBUTE = 'data-workbench-element';

/**
 * The attribute name a stage element carries its id in, from what the host passed: a name (`data-block-id`) or an attribute selector (`[data-block-id]`).
 * Anything that is not a valid attribute name falls back to the default, so a typo cannot inject a selector.
 */
export function elementAttributeName(value: string | null | undefined): string {
    const name = (value ?? '')
        .trim()
        .replace(/^\[/, '')
        .replace(/\]$/, '')
        .trim();
    return /^[A-Za-z_][-A-Za-z0-9_.:]*$/.test(name)
        ? name
        : DEFAULT_ELEMENT_ATTRIBUTE;
}

/** The rects of the selected elements drawn in the viewport (each carrying `attribute` with a matching id); an id with nothing drawn is skipped. */
export function selectedRects(
    viewport: HTMLElement,
    ids: readonly string[],
    attribute: string = DEFAULT_ELEMENT_ATTRIBUTE,
): AnchorRect[] {
    const name = elementAttributeName(attribute);
    return [...viewport.querySelectorAll<HTMLElement>(`[${name}]`)]
        .filter((el) => ids.includes(el.getAttribute(name) ?? ''))
        .map((el) => {
            const box = el.getBoundingClientRect();
            return {
                top: box.top,
                left: box.left,
                width: box.width,
                height: box.height,
            };
        });
}
