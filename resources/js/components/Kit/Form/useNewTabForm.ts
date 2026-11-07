import { useForm } from '@inertiajs/vue3';
import { submitHiddenForm, type HiddenFormValue } from './hiddenForm';

// Validate on the server first, then open the result (docs/form-link.md): an Inertia form posts to `validateUrl`; only when it succeeds does a hidden GET form
// open `targetUrl`, in a new tab by default. The hidden form is built on <body>, not inside the component, because a validating POST that redirects back
// can unmount a modal (and everything in it) before onSuccess runs. Browsers allow the new tab only shortly after the click, so keep validation quick; for a
// slow job, queue it and download the result from where it lands instead.
export type NewTabFormOptions = {
    // Where the result opens: `_blank` (the default) or `_self` for a download.
    target?: string;
    // Fields for the GET that opens the result: by default none (the target URL carries what it needs).
    data?:
        | Record<string, HiddenFormValue>
        | ((page: unknown) => Record<string, HiddenFormValue>);
    // Runs after the result is opened: close the modal here (useModal().close).
    onOpened?: () => void;
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- the fields are the caller's, as useForm's own are
export function useNewTabForm<T extends Record<string, any>>(payload: T) {
    const form = useForm<T>(payload);

    function open(
        validateUrl: string,
        targetUrl: string | ((page: unknown) => string),
        options: NewTabFormOptions = {},
    ): void {
        form.post(validateUrl, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page: unknown) => {
                const url =
                    typeof targetUrl === 'function'
                        ? targetUrl(page)
                        : targetUrl;
                const data =
                    typeof options.data === 'function'
                        ? options.data(page)
                        : options.data;
                submitHiddenForm({
                    url,
                    method: 'get',
                    data,
                    target: options.target ?? '_blank',
                });
                options.onOpened?.();
            },
        });
    }

    return { form, open };
}
