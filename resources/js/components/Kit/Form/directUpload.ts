/*
 * Direct-to-bucket upload (docs/media.md): declare the file (type, size, SHA-256), PUT it straight to the presigned URL with progress, then confirm.
 * A `POST` ticket (the disk cannot presign) sends the file as multipart to the confirm route instead; a `null` ticket (already stored) only confirms.
 * One mutation id per File, so a retry of the same file replays the same upload. Every step that is not a 2xx rejects with a DirectUploadError.
 */
export type DirectRoutes = { request: string; confirm: string };
export type UploadTicket = {
    id: string;
    status: string;
    method: 'PUT' | 'POST' | null;
    url: string | null;
    headers: Record<string, string>;
    expires_at: string | null;
};
export type DirectTransport = {
    json: (url: string, body: object) => Promise<{ status: number; body: any }>;
    form: (
        url: string,
        body: FormData,
    ) => Promise<{ status: number; body: any }>;
    put: (
        url: string,
        file: Blob,
        headers: Record<string, string>,
        progress: (loaded: number) => void,
    ) => Promise<number>;
    digest: (file: Blob) => Promise<string>;
};
export class DirectUploadError extends Error {
    step: 'request' | 'upload' | 'confirm';
    status: number;
    body?: unknown;
    constructor(
        step: 'request' | 'upload' | 'confirm',
        status: number,
        body?: unknown,
    ) {
        super(`Upload failed at ${step} (${status})`);
        this.step = step;
        this.status = status;
        this.body = body;
    }
}

const mutations = new WeakMap<Blob, string>();
export function mutationFor(file: Blob): string {
    let id = mutations.get(file);
    if (!id) {
        id = crypto.randomUUID();
        mutations.set(file, id);
    }
    return id;
}

function xsrf(): Record<string, string> {
    const token =
        typeof document === 'undefined'
            ? undefined
            : document.cookie
                  .split('; ')
                  .find((row) => row.startsWith('XSRF-TOKEN='))
                  ?.slice(11);
    return token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {};
}
async function send(
    url: string,
    body: BodyInit,
    headers: Record<string, string>,
) {
    const response = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...xsrf(), ...headers },
    });
    return {
        status: response.status,
        body: await response.json().catch(() => null),
    };
}
export const browserTransport: DirectTransport = {
    json: (url, body) =>
        send(url, JSON.stringify(body), { 'Content-Type': 'application/json' }),
    form: (url, body) => send(url, body, {}),
    put: (url, file, headers, progress) =>
        new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            request.open('PUT', url);
            for (const [name, value] of Object.entries(headers))
                request.setRequestHeader(name, value);
            request.upload.onprogress = (event) => progress(event.loaded);
            request.onload = () => resolve(request.status);
            request.onerror = () => reject(new DirectUploadError('upload', 0));
            request.send(file);
        }),
    digest: async (file) =>
        Array.from(
            new Uint8Array(
                await crypto.subtle.digest('SHA-256', await file.arrayBuffer()),
            ),
        )
            .map((byte) => byte.toString(16).padStart(2, '0'))
            .join(''),
};

const ok = (status: number) => status >= 200 && status < 300;

/** Uploads one file; `progress` gets 0..1. Resolves with the confirm route's JSON. */
export async function directUpload(
    file: File,
    routes: DirectRoutes,
    progress: (fraction: number) => void = () => {},
    transport: DirectTransport = browserTransport,
): Promise<any> {
    const sha256 = await transport.digest(file);
    const requested = await transport.json(routes.request, {
        mime: file.type,
        bytes: file.size,
        sha256,
        mutation_id: mutationFor(file),
    });
    if (!ok(requested.status))
        throw new DirectUploadError(
            'request',
            requested.status,
            requested.body,
        );
    const ticket = requested.body as UploadTicket;
    let confirmed;
    if (ticket.method === 'PUT' && ticket.url) {
        const status = await transport.put(
            ticket.url,
            file,
            ticket.headers,
            (loaded) => progress(file.size ? loaded / file.size : 1),
        );
        if (!ok(status)) throw new DirectUploadError('upload', status);
        confirmed = await transport.json(routes.confirm, { id: ticket.id });
    } else if (ticket.method === 'POST') {
        const body = new FormData();
        body.append('id', ticket.id);
        body.append('file', file);
        confirmed = await transport.form(routes.confirm, body);
    } else {
        confirmed = await transport.json(routes.confirm, { id: ticket.id });
    }
    if (!ok(confirmed.status))
        throw new DirectUploadError(
            'confirm',
            confirmed.status,
            confirmed.body,
        );
    progress(1);
    return confirmed.body;
}
