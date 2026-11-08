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
    state: 'ready' | 'empty' | 'error';
    empty: { title: string; description: string; action: WidgetAction | null } | null;
    error: { message: string; retry: WidgetAction | null } | null;
    actions: WidgetAction[];
    [prop: string]: unknown;
};
