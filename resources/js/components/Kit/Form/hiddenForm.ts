// A native form built on <body> and submitted from script (docs/form-link.md): a download or a new-tab submit that the browser makes itself, so the response is
// a real navigation (a file save, a tab) rather than an Inertia or fetch response. The form belongs to the document, not to a component, so it is still
// there when the component that asked for it (a modal's button, say) has been unmounted.
export type HiddenFormMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';
export type HiddenFormValue =
    | string
    | number
    | boolean
    | null
    | undefined
    | HiddenFormValue[]
    | { [key: string]: HiddenFormValue };
export type HiddenFormOptions = {
    url: string;
    method?: HiddenFormMethod;
    data?: Record<string, HiddenFormValue>;
    // `_self` (the default) for a download: a response with Content-Disposition: attachment saves the file and leaves the page where it is.
    // `_blank` for a new tab; browsers allow that only during a user gesture (a click, or a few seconds after one).
    target?: string;
    // The CSRF token for a non-GET submit. Defaults to the page's <meta name="csrf-token">; without one, Laravel accepts a same-origin form by its
    // Sec-Fetch-Site header (PreventRequestForgery).
    token?: string;
};

/** Flattens data into the field names a Laravel request reads back: `a[b]`, `list[0]`. Booleans are 1 and 0; null and undefined are empty. */
export function hiddenFields(
    data: Record<string, HiddenFormValue>,
    prefix = '',
): [string, string][] {
    const fields: [string, string][] = [];
    for (const [key, value] of Object.entries(data)) {
        const name = prefix === '' ? key : `${prefix}[${key}]`;
        if (Array.isArray(value)) {
            fields.push(
                ...hiddenFields(
                    Object.fromEntries(
                        value.map((item, index) => [String(index), item]),
                    ),
                    name,
                ),
            );
        } else if (value !== null && typeof value === 'object') {
            if (
                typeof Blob !== 'undefined' &&
                (value as unknown) instanceof Blob
            )
                throw new TypeError(
                    `submitHiddenForm: ${name} is a file, which a hidden form cannot carry. Upload it with the form, then link to the result.`,
                );
            fields.push(...hiddenFields(value, name));
        } else {
            fields.push([
                name,
                value === true
                    ? '1'
                    : value === false
                      ? '0'
                      : value === null || value === undefined
                        ? ''
                        : String(value),
            ]);
        }
    }

    return fields;
}

/** Builds the form on <body>, submits it and removes it. GET keeps the URL's own query, which a browser drops from a GET form's action. */
export function submitHiddenForm({
    url,
    method = 'get',
    data = {},
    target = '_self',
    token,
}: HiddenFormOptions): void {
    const form = document.createElement('form');
    const action = new URL(url, location.href);
    const fields = hiddenFields(data);
    if (method === 'get') {
        fields.unshift(
            ...[...action.searchParams.entries()].filter(
                ([name]) => !fields.some(([field]) => field === name),
            ),
        );
        action.search = '';
    } else {
        if (method !== 'post') fields.push(['_method', method.toUpperCase()]);
        const csrf =
            token ??
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content;
        if (csrf) fields.push(['_token', csrf]);
    }
    form.method = method === 'get' ? 'get' : 'post';
    form.action = action.href;
    form.target = target;
    form.hidden = true;
    form.dataset.hiddenForm = '';
    for (const [name, value] of fields) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
    form.remove();
}
