import { inject, type InjectionKey } from 'vue';
import type { MenuAlign } from '@/components/Kit/Menu/menuAlign';

export { alignThatFits } from '@/components/Kit/Menu/menuAlign';
export type { MenuAlign } from '@/components/Kit/Menu/menuAlign';

// The context every kit menu item reads (MenuPanel.md): written here from the spec, on Reka's dropdown menu. `hold`/`release` count a dialog that belongs to an item, so the menu stays open behind it.
export interface MenuPanelApi {
    close: () => void;
    hold: () => void;
    release: () => void;
    isHeld: () => boolean;
    openFromEnd: (event: KeyboardEvent) => void;
    setTrigger: (el: HTMLElement | null) => void;
}

export const menuPanelKey: InjectionKey<MenuPanelApi> = Symbol('menuPanel');

/** The enclosing menu, or `undefined` outside one. */
export const useMenuPanel = () => inject(menuPanelKey, undefined);

/** Whether the element, or an ancestor, can take focus. */
export function isFocusableLoose(element: Element | null): boolean {
    return !!element?.closest('a[href], button, input, select, textarea, summary, [tabindex]:not([tabindex="-1"]), [contenteditable=""], [contenteditable="true"]');
}

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/** Moves focus to the next (1) or previous (-1) focusable element after `from` in document order, skipping any that refuse focus. */
export function focusNeighbour(from: HTMLElement, step: 1 | -1): void {
    const all = [...document.querySelectorAll<HTMLElement>(FOCUSABLE)].filter((el) => !el.closest('[role=menu]') && el.getClientRects().length > 0);
    const at = all.indexOf(from);
    const ordered = at === -1 ? all.filter((el) => (from.compareDocumentPosition(el) & (step === 1 ? Node.DOCUMENT_POSITION_FOLLOWING : Node.DOCUMENT_POSITION_PRECEDING)) !== 0) : all.slice(step === 1 ? at + 1 : 0, step === 1 ? undefined : at);
    const queue = step === 1 ? ordered : [...ordered].reverse();

    for (const el of queue) {
        el.focus();
        if (document.activeElement === el) return;
    }
}

/**
 * What an item's row needs so that Enter and Space do exactly what a click does. Reka sends a keyboard choice to the row itself, not to the control inside it, so the click is caught on the row (capture) and passed on to the
 * first button or real link inside; a link at an empty or `#` href is left alone. A pointer click already lands on the control. After a choice the menu closes, unless the item holds it for a dialog.
 */
export function useMenuItem() {
    const menu = useMenuPanel();

    return {
        onClickCapture(event: MouseEvent) {
            const row = event.currentTarget as HTMLElement;
            if (event.target !== row) return;

            const control = row.querySelector<HTMLElement>('button, a[href]');
            const href = control?.getAttribute('href');
            if (!control || href === '' || href === '#') return;

            event.stopPropagation();
            control.click();
        },
        onSelect(event: Event) {
            if (menu?.isHeld()) event.preventDefault();
        },
    };
}
