// Where a list of server action descriptors goes: inline or in a menu. Nothing here checks a permission: a forbidden action is absent from the list.
// `intent` is `dialog`, `document` or `edit` (an action that changes the record; ActionBar hides it inside a locked LockedForm).
export type ActionDescriptor = {
    label: string; url: string; method: string; key?: string | null; disabled?: boolean; reason?: string | null; destructive?: boolean;
    confirm?: { title: string; description: string; label: string } | null; intent?: string | null; group?: string | null; menu?: boolean; icon?: string | null; newTab?: boolean | null;
};
export type PlaceableAction = { method: string; key?: string | null; intent?: string | null; destructive?: boolean };

/** Ordinary GET navigation; a dialog-intent GET or an intercepted key is an operation. */
export function isNavigation(action: PlaceableAction, intercept: string[] = []): boolean {
    return action.method.toLowerCase() === 'get' && action.intent !== 'dialog' && !intercept.includes(action.key ?? '');
}

/**
 * Without declared groups: navigation stays inline whatever the limit; one or two operations stay inline, three or more show the first (an explicit limit, clamped
 * at zero, counts operations only); a destructive operation goes in the menu when a non-destructive action exists, and stays inline when only destructive ones do.
 */
export function placeActions<T extends PlaceableAction>(actions: T[], inlineLimit?: number, intercept: string[] = []): { inline: T[]; menu: T[] } {
    const operations = actions.filter(action => !isNavigation(action, intercept) && !action.destructive);
    const destructive = actions.filter(action => action.destructive && !isNavigation(action, intercept));
    const limit = Math.max(0, inlineLimit ?? (operations.length >= 3 ? 1 : operations.length));
    const visible = new Set(operations.slice(0, limit));
    if (!actions.some(action => !action.destructive)) destructive.forEach(action => visible.add(action));
    return {
        inline: actions.filter(action => isNavigation(action, intercept) || visible.has(action)),
        menu: actions.filter(action => !isNavigation(action, intercept) && !visible.has(action)),
    };
}

export type GroupedAction = PlaceableAction & { group?: string | null; menu?: boolean };
export type ActionLayout<T> = { kind: 'group'; key: string; inline: T[]; menu: T[] } | { kind: 'single'; key: string; action: T };

/**
 * Declared layout: actions sharing a `group` join one group (adjacent or not), its `menu` actions in that group's menu; ungrouped actions stand alone. Groups
 * carrying a menu move after every other part, keeping their relative order and their own menus. Null when no action declares a group (`menu` alone does not count).
 */
export function layoutActions<T extends GroupedAction>(actions: T[]): ActionLayout<T>[] | null {
    if (!actions.some(action => action.group)) return null;
    const layout: ActionLayout<T>[] = [];
    const groups = new Map<string, { kind: 'group'; key: string; inline: T[]; menu: T[] }>();
    actions.forEach((action, index) => {
        if (!action.group) { layout.push({ kind: 'single', key: `single-${index}`, action }); return; }
        let group = groups.get(action.group);
        if (!group) { group = { kind: 'group', key: action.group, inline: [], menu: [] }; groups.set(action.group, group); layout.push(group); }
        (action.menu ? group.menu : group.inline).push(action);
    });
    const withMenu = (part: ActionLayout<T>) => part.kind === 'group' && part.menu.length > 0;
    return [...layout.filter(part => !withMenu(part)), ...layout.filter(withMenu)];
}

/** The icon name an action shows: its own, else `plus` for a label starting Add, New or Create, else the menu's key fallbacks (`edit`, `payment`). */
export function actionIconName(action: { label: string; icon?: string | null; key?: string | null }, menu = false): string | null {
    if (action.icon) return action.icon;
    if (/^(Add|New|Create)\b/.test(action.label)) return 'plus';
    if (menu) return ({ edit: 'pencil', payment: 'credit-card' } as Record<string, string>)[action.key ?? ''] ?? null;
    return null;
}

/** Whether the label shows the new-tab arrow: an explicit `newTab`, else document intent. It describes the link; it does not change navigation. */
export function opensNewTab(action: { newTab?: boolean | null; intent?: string | null }): boolean {
    return action.newTab ?? action.intent === 'document';
}
