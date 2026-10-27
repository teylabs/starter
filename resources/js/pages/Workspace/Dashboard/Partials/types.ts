// A resolved widget's frame (`Dashboard\Widget`), with whatever typed props its own class adds.
export type WidgetAction = { label: string; url: string; method: string; key: string | null };
export type WidgetFrame = {
    key: string;
    title: string;
    component: string;
    span: { md: number; lg: number };
    state: 'ready' | 'empty' | 'error';
    empty: { title: string; description: string; action: WidgetAction | null } | null;
    error: { message: string; retry: WidgetAction | null } | null;
    actions: WidgetAction[];
    [prop: string]: unknown;
};
