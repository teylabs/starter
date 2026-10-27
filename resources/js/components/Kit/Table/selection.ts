/*
 * The DataTable's selection model, plain data with no Vue: the ids picked so far, kept across pages, sorts and filters until cleared (the bulk bar always says
 * how many). The page header checkbox reads `pageState` (all, some or none of the visible rows) and `togglePage` selects or clears only the visible rows. A bulk
 * action sends `ids` and the server re-checks every one (TableSelection), so a stale id refuses the action rather than acting on part of it.
 */
export type Id = string | number;
export type Selection = { ids: Id[] };
export type PageState = 'all' | 'some' | 'none';

const key = (id: Id) => String(id);

export function emptySelection(): Selection {
    return { ids: [] };
}

export function isSelected(selection: Selection, id: Id): boolean {
    return selection.ids.some((picked) => key(picked) === key(id));
}

export function toggle(selection: Selection, id: Id): Selection {
    return isSelected(selection, id) ? { ids: selection.ids.filter((picked) => key(picked) !== key(id)) } : { ids: [...selection.ids, id] };
}

export function pageState(selection: Selection, page: Id[]): PageState {
    const picked = page.filter((id) => isSelected(selection, id)).length;
    return page.length > 0 && picked === page.length ? 'all' : picked > 0 ? 'some' : 'none';
}

/** Every visible row selected clears the visible rows; otherwise selects them (keeping rows picked on other pages). */
export function togglePage(selection: Selection, page: Id[]): Selection {
    if (pageState(selection, page) === 'all') {
        const visible = new Set(page.map(key));
        return { ids: selection.ids.filter((picked) => !visible.has(key(picked))) };
    }
    return { ids: [...selection.ids, ...page.filter((id) => !isSelected(selection, id))] };
}
