// A resolved widget's frame (`Dashboard\Widget`), with whatever typed props its own class adds.
export type WidgetAction = { label: string; url: string; method: string; key: string | null };
export type WidgetFrame = {
    key: string;
    title: string;
    component: string;
    /** What the card is; placement follows it by convention (task and feed in main, status and setup in aside), never by code. */
    kind: 'task' | 'feed' | 'status' | 'setup';
    /** The column the card stacks in: `main` (primary work) or `aside` (context). */
    region: 'main' | 'aside';
    /** @deprecated No longer drives the layout; set `region` on the widget instead. */
    span: { md: number; lg: number };
    /** The body's minimum height while ready (a CSS length such as `18rem`), so the card holds its place while deferred content loads. Null or absent: natural height. */
    loadingHeight?: string | null;
    state: 'ready' | 'empty' | 'error';
    empty: { title: string; description: string; action: WidgetAction | null } | null;
    error: { message: string; retry: WidgetAction | null } | null;
    actions: WidgetAction[];
    /** The header's Sort by control: the widget's declared orders, the viewer's current one, where a choice is saved and the props it reloads. Null without one. */
    sortBy?: { label: string; options: { key: string; label: string }[]; current: string; url: string; method: string; reload: string[] } | null;
    [prop: string]: unknown;
};
