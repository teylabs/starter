// Reading a JSON body from Inertia's HTTP client (`http.getClient().request`, `http.request`, useHttp). Inertia v3's XHR client resolves `data` as the raw
// responseText string, not parsed JSON; a host's own client (axios, say) may hand back an object. Kit code reads bodies through here, never `response.data.x`.

export type HttpBody = {
    data?: unknown;
    headers?: Record<string, string | undefined>;
};
export type ResponseJsonOptions = { allowEmpty?: boolean };

/** Why a body could not be read as JSON: empty when the caller needs one, or text that is not JSON. */
export class ResponseJsonError extends Error {
    readonly body: string;
    constructor(message: string, body: string) {
        super(message);
        this.body = body;
        this.name = 'ResponseJsonError';
    }
}

/**
 * The response's parsed JSON body. An object (or array) `data` comes back as it is. A string is parsed as JSON whatever the content type says, so a body with a
 * JSON content type or none both read; text that is not JSON throws a ResponseJsonError naming the content type, never a silent null. An empty body (no `data`,
 * or a blank string) throws too, unless the caller passes `allowEmpty`, which returns null: only for a request whose caller expects no body.
 */
export function responseJson(
    response: HttpBody,
    options: ResponseJsonOptions = {},
): unknown {
    const data = response.data;
    if (data !== null && typeof data === 'object') return data;
    const text =
        typeof data === 'string'
            ? data
            : data === null || data === undefined
              ? ''
              : String(data);
    if (text.trim() === '') {
        if (options.allowEmpty) return null;
        throw new ResponseJsonError(
            'The response had an empty body where JSON was expected.',
            text,
        );
    }
    try {
        return JSON.parse(text);
    } catch {
        const type = contentType(response.headers);
        throw new ResponseJsonError(
            `The response body is not JSON (content type: ${type || 'none'}).`,
            text,
        );
    }
}

/**
 * The JSON body of a refused request (Inertia's HttpResponseError, or any error with a `response`), for reading its refusal code or stored state. The status has
 * already decided the outcome, so an empty or non-JSON error body (an HTML error page) reads as null here instead of throwing.
 */
export function errorJson(error: unknown): {
    status: number | undefined;
    body: unknown;
} {
    const response = (
        error as { response?: HttpBody & { status?: number } } | null
    )?.response;
    if (!response) return { status: undefined, body: null };
    try {
        return {
            status: response.status,
            body: responseJson(response, { allowEmpty: true }),
        };
    } catch {
        return { status: response.status, body: null };
    }
}

function contentType(headers: HttpBody['headers']): string {
    if (!headers) return '';
    const key = Object.keys(headers).find(
        (name) => name.toLowerCase() === 'content-type',
    );
    return key ? String(headers[key] ?? '') : '';
}
