// Where a list of server action descriptors goes: inline or in a menu. Nothing here checks a permission: a forbidden action is absent from the list.
// `intent` is `dialog`, `panel` (opened by the host, like a dialog: always a button that emits, never a link), `document` or `edit` (an action that changes the record; ActionBar hides it inside a locked LockedForm). `checked` (true or false) makes a menu
// row a checkbox item (role="menuitemcheckbox" with aria-checked); choosing it emits the action as any other, and the host flips `checked`. Null or absent: a plain row.
// `ariaLabel` is the accessible name when the visible label, a verb, needs its record ("Remove Billing").
export type ActionDescriptor = {
    label: string;
    url: string;
    method: string;
    key?: string | null;
    disabled?: boolean;
    reason?: string | null;
    ariaLabel?: string | null;
    description?: string | null;
    destructive?: boolean;
    confirm?: {
        title?: string | null;
        description: string;
        label: string;
        typed?: string | null;
    } | null;
    intent?: string | null;
    group?: string | null;
    menu?: boolean;
    icon?: string | null;
    newTab?: boolean | null;
    priority?: 'primary' | 'secondary' | 'overflow' | null;
    checked?: boolean | null;
};
export type PlaceableAction = {
    method: string;
    key?: string | null;
    intent?: string | null;
    destructive?: boolean;
};

/** The intents the host opens itself (a dialog, a panel): a GET with one is an operation, rendered as a button, never a link. */
const HOST_OPENED = ['dialog', 'panel'];

/** Ordinary GET navigation; a dialog- or panel-intent GET or an intercepted key is an operation. */
export function isNavigation(
    action: PlaceableAction,
    intercept: string[] = [],
): boolean {
    return (
        action.method.toLowerCase() === 'get' &&
        !HOST_OPENED.includes(action.intent ?? '') &&
        !intercept.includes(action.key ?? '')
    );
}

/**
 * Whether an action renders as a link (an Inertia Link, or an <a> for a new tab) rather than a button: a GET that is not a dialog or panel, asks no confirmation, is not
 * disabled and is not intercepted. A link can be opened in a new tab, copied and is announced as a link; everything else stays a button.
 */
export function rendersAsLink(
    action: {
        method: string;
        intent?: string | null;
        key?: string | null;
        confirm?: unknown;
        disabled?: boolean;
    },
    intercept: string[] = [],
): boolean {
    return (
        action.method.toLowerCase() === 'get' &&
        !HOST_OPENED.includes(action.intent ?? '') &&
        !action.confirm &&
        !action.disabled &&
        !intercept.includes(action.key ?? '')
    );
}

/**
 * Without declared groups: navigation stays inline whatever the limit; one or two operations stay inline, three or more show the first (an explicit limit, clamped
 * at zero, counts operations only); a destructive operation goes in the menu when a non-destructive action exists, and stays inline when only destructive ones do.
 */
export function placeActions<T extends PlaceableAction>(
    actions: T[],
    inlineLimit?: number,
    intercept: string[] = [],
): { inline: T[]; menu: T[] } {
    const operations = actions.filter(
        (action) => !isNavigation(action, intercept) && !action.destructive,
    );
    const destructive = actions.filter(
        (action) => action.destructive && !isNavigation(action, intercept),
    );
    const limit = Math.max(
        0,
        inlineLimit ?? (operations.length >= 3 ? 1 : operations.length),
    );
    const visible = new Set(operations.slice(0, limit));
    if (!actions.some((action) => !action.destructive))
        destructive.forEach((action) => visible.add(action));
    return {
        inline: actions.filter(
            (action) => isNavigation(action, intercept) || visible.has(action),
        ),
        menu: actions.filter(
            (action) =>
                !isNavigation(action, intercept) && !visible.has(action),
        ),
    };
}

export type GroupedAction = PlaceableAction & {
    group?: string | null;
    menu?: boolean;
};
export type ActionLayout<T> =
    | { kind: 'group'; key: string; inline: T[]; menu: T[] }
    | { kind: 'single'; key: string; action: T };

/**
 * Declared layout: actions sharing a `group` join one group (adjacent or not), its `menu` actions in that group's menu; ungrouped actions stand alone. Groups
 * carrying a menu move after every other part, keeping their relative order and their own menus. Null when no action declares a group (`menu` alone does not count).
 */
export function layoutActions<T extends GroupedAction>(
    actions: T[],
): ActionLayout<T>[] | null {
    if (!actions.some((action) => action.group)) return null;
    const layout: ActionLayout<T>[] = [];
    const groups = new Map<
        string,
        { kind: 'group'; key: string; inline: T[]; menu: T[] }
    >();
    actions.forEach((action, index) => {
        if (!action.group) {
            layout.push({ kind: 'single', key: `single-${index}`, action });
            return;
        }
        let group = groups.get(action.group);
        if (!group) {
            group = { kind: 'group', key: action.group, inline: [], menu: [] };
            groups.set(action.group, group);
            layout.push(group);
        }
        (action.menu ? group.menu : group.inline).push(action);
    });
    const withMenu = (part: ActionLayout<T>) =>
        part.kind === 'group' && part.menu.length > 0;
    return [
        ...layout.filter((part) => !withMenu(part)),
        ...layout.filter(withMenu),
    ];
}

/** A menu row's role: `menuitemcheckbox` when the action's `checked` is a boolean (either way), else `menuitem`. */
export function menuItemRole(action: {
    checked?: boolean | null;
}): 'menuitemcheckbox' | 'menuitem' {
    return typeof action.checked === 'boolean'
        ? 'menuitemcheckbox'
        : 'menuitem';
}

/**
 * The icon name an action shows: its own, else `plus` for a label starting Add, New or Create, else, in a menu, the common intents by key, then by label:
 * `edit` pencil, `payment` credit-card, `reopen`/`re-open` lock-open, `cancel`/`decline` x, `merge` merge, `delete`/`remove` trash, `download` download. An explicit `icon` always wins.
 */
const menuKeyIcons = new Map<string, string>(
    Object.entries({
        edit: 'pencil',
        payment: 'credit-card',
        reopen: 'lock-open',
        're-open': 'lock-open',
        cancel: 'x',
        decline: 'x',
        merge: 'merge',
        delete: 'trash',
        remove: 'trash',
        download: 'download',
    }),
);
const menuLabelIcons: [RegExp, string][] = [
    [/^Edit\b/i, 'pencil'],
    [/^Re-?open\b/i, 'lock-open'],
    [/^(Cancel|Decline)\b/i, 'x'],
    [/^Merge\b/i, 'merge'],
    [/^(Delete|Remove)\b/i, 'trash'],
    [/^Download\b/i, 'download'],
];
export function actionIconName(
    action: { label: string; icon?: string | null; key?: string | null },
    menu = false,
): string | null {
    if (action.icon) return action.icon;
    if (/^(Add|New|Create)\b/.test(action.label)) return 'plus';
    if (!menu) return null;
    const key = (action.key ?? '').toLowerCase();
    return (
        menuKeyIcons.get(key) ??
        menuLabelIcons.find(([pattern]) => pattern.test(action.label))?.[1] ??
        null
    );
}

/** Whether the label shows the new-tab arrow: an explicit `newTab`, else document intent. It describes the link; it does not change navigation. */
export function opensNewTab(action: {
    newTab?: boolean | null;
    intent?: string | null;
}): boolean {
    return action.newTab ?? action.intent === 'document';
}
