import { inject, provide, type InjectionKey } from 'vue';

// A host's own classes for every row of a menu (its row height, say), from ActionBar's or ButtonGroup's `itemClass`. ButtonGroup provides it to the rows in its
// #menu (ActionMenuItems, DropdownItem, DropdownLink), which add it after their own classes; a row's destructive and disabled classes still apply.
const menuItemClassKey: InjectionKey<() => string | undefined> = Symbol(
    'kit-menu-item-class',
);

export function provideMenuItemClass(
    itemClass: () => string | undefined,
): void {
    provide(menuItemClassKey, itemClass);
}

export function useMenuItemClass(): () => string | undefined {
    return inject(menuItemClassKey, () => undefined);
}
