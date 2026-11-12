/*
 * Direct-to-bucket upload (docs/media.md): declare the file (type, size, SHA-256), PUT it straight to the presigned URL with progress, then confirm.
 * A `POST` ticket (the disk cannot presign) sends the file as multipart to the confirm route instead; a `null` ticket (already stored) only confirms.
 * The request, confirm and complete bodies carry `file_name` (the File's own name), for ClientFileName::attach() on the server; never trusted for type or key.
 * One mutation id per File, so a retry of the same file replays the same upload. Every step that is not a 2xx rejects with a DirectUploadError.
 * With a `complete` route the client offers S3 multipart (docs/media.md › Large files): a `MULTIPART` ticket is uploaded in parts, a few at a time
 * with retries, then the parts' ETags go to `complete`. A failed or cancelled multipart upload is sent to `abort` when the host gives one.
 */
export type DirectRoutes = {
    request: string;
    confirm: string;
    complete?: string;
    abort?: string;
};
export type UploadPart = {
    part: number;
    url: string | null;
    headers: Record<string, string>;
};
export type UploadTicket = {
    id: string;
    status: string;
    method: 'PUT' | 'POST' | 'MULTIPART' | null;
    url: string | null;
    headers: Record<string, string>;
    expires_at: string | null;
    upload_id?: string;
    parts?: UploadPart[];
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
    /** One multipart part: resolves with the status and the ETag response header (the bucket's CORS must expose ETag). */
    putPart?: (
        url: string,
        part: Blob,
        headers: Record<string, string>,
        progress: (loaded: number) => void,
    ) => Promise<{ status: number; etag: string | null }>;
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
    putPart: (url, part, headers, progress) =>
        new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            request.open('PUT', url);
            for (const [name, value] of Object.entries(headers))
                request.setRequestHeader(name, value);
            request.upload.onprogress = (event) => progress(event.loaded);
            request.onload = () =>
                resolve({
                    status: request.status,
                    etag: request.getResponseHeader('ETag'),
                });
            request.onerror = () => reject(new DirectUploadError('upload', 0));
            request.send(part);
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

/** Parts uploaded at once, and tries per part, for a multipart upload. */
export const PART_CONCURRENCY = 4;
export const PART_ATTEMPTS = 3;

/** The server's part layout: `ceil(size / count)` bytes each, the last taking the rest. */
export function partSlices(file: Blob, count: number): Blob[] {
    const size = Math.ceil(file.size / count);
    return Array.from({ length: count }, (_, index) =>
        file.slice(index * size, Math.min(file.size, (index + 1) * size)),
    );
}

function hexToBase64(hex: string): string {
    let binary = '';
    for (let index = 0; index < hex.length; index += 2)
        binary += String.fromCharCode(
            parseInt(hex.slice(index, index + 2), 16),
        );
    return btoa(binary);
}

/** Runs `tasks` with at most `limit` in flight. After a failure no task is started, and it rejects with the first failure only once every running task has settled. */
async function pooled<T>(
    tasks: (() => Promise<T>)[],
    limit: number,
): Promise<T[]> {
    const results: T[] = new Array(tasks.length);
    let next = 0;
    let failed = false;
    let failure: unknown;
    const worker = async () => {
        while (!failed && next < tasks.length) {
            const index = next++;
            try {
                results[index] = await tasks[index]();
            } catch (error) {
                if (!failed) failure = error;
                failed = true;
            }
        }
    };
    await Promise.allSettled(
        Array.from({ length: Math.min(limit, tasks.length) }, worker),
    );
    if (failed) throw failure;
    return results;
}

async function uploadParts(
    file: File,
    ticket: UploadTicket,
    routes: DirectRoutes,
    requestBody: object,
    progress: (fraction: number) => void,
    transport: DirectTransport,
    signal?: AbortSignal,
): Promise<{ part: number; etag: string; checksum: string | null }[]> {
    const putPart = transport.putPart;
    if (!putPart) throw new DirectUploadError('upload', 0);
    let parts = ticket.parts ?? [];
    const slices = partSlices(file, parts.length);
    // With part checksums on, the first ticket has no URLs: hash each part and ask again with the same mutation id to have them signed.
    if (parts.some((part) => !part.url)) {
        const part_sha256: string[] = [];
        for (const slice of slices)
            part_sha256.push(await transport.digest(slice));
        const again = await transport.json(routes.request, {
            ...requestBody,
            part_sha256,
        });
        if (!ok(again.status) || again.body?.method !== 'MULTIPART')
            throw new DirectUploadError('request', again.status, again.body);
        parts = (again.body as UploadTicket).parts ?? [];
        if (parts.length !== slices.length || parts.some((part) => !part.url))
            throw new DirectUploadError('request', again.status, again.body);
    }
    const loaded = new Array(slices.length).fill(0);
    const report = () =>
        progress(
            file.size
                ? loaded.reduce((sum, value) => sum + value, 0) / file.size
                : 1,
        );
    return pooled(
        parts.map((part, index) => async () => {
            let last = 0;
            for (let attempt = 1; attempt <= PART_ATTEMPTS; attempt++) {
                if (signal?.aborted) throw new DirectUploadError('upload', 0);
                loaded[index] = 0;
                try {
                    const result = await putPart(
                        part.url as string,
                        slices[index],
                        part.headers,
                        (bytes) => {
                            loaded[index] = bytes;
                            report();
                        },
                    );
                    last = result.status;
                    if (ok(result.status)) {
                        // No ETag means the bucket's answer can't be proven: fail closed rather than complete without it.
                        if (!result.etag)
                            throw new DirectUploadError(
                                'upload',
                                result.status,
                            );
                        loaded[index] = slices[index].size;
                        report();
                        return {
                            part: part.part,
                            etag: result.etag,
                            checksum:
                                part.headers['x-amz-checksum-sha256'] ?? null,
                        };
                    }
                } catch (error) {
                    if (
                        error instanceof DirectUploadError &&
                        error.status !== 0
                    )
                        throw error;
                }
            }
            throw new DirectUploadError('upload', last);
        }),
        PART_CONCURRENCY,
    );
}

/** Uploads one file; `progress` gets 0..1. Resolves with the confirm route's JSON. */
export async function directUpload(
    file: File,
    routes: DirectRoutes,
    progress: (fraction: number) => void = () => {},
    transport: DirectTransport = browserTransport,
    signal?: AbortSignal,
): Promise<any> {
    const sha256 = await transport.digest(file);
    const requestBody = {
        mime: file.type,
        bytes: file.size,
        sha256,
        mutation_id: mutationFor(file),
        file_name: file.name,
        ...(routes.complete ? { multipart: true } : {}),
    };
    const requested = await transport.json(routes.request, requestBody);
    if (!ok(requested.status))
        throw new DirectUploadError(
            'request',
            requested.status,
            requested.body,
        );
    const ticket = requested.body as UploadTicket;
    let confirmed;
    if (ticket.method === 'MULTIPART' && routes.complete) {
        let parts;
        try {
            parts = await uploadParts(
                file,
                ticket,
                routes,
                requestBody,
                progress,
                transport,
                signal,
            );
            // Cancelled while the last parts were in flight: abort rather than complete.
            if (signal?.aborted) throw new DirectUploadError('upload', 0);
        } catch (error) {
            if (routes.abort)
                await transport
                    .json(routes.abort, { id: ticket.id })
                    .catch(() => null);
            throw error;
        }
        try {
            confirmed = await transport.json(routes.complete, {
                id: ticket.id,
                file_name: file.name,
                parts,
            });
        } catch (error) {
            if (routes.abort)
                await transport
                    .json(routes.abort, { id: ticket.id })
                    .catch(() => null);
            throw error;
        }
        // A failed or refused complete leaves the multipart upload open on storage: abort it, as a failed part does.
        if (!ok(confirmed.status) && routes.abort)
            await transport
                .json(routes.abort, { id: ticket.id })
                .catch(() => null);
    } else if (ticket.method === 'PUT' && ticket.url) {
        const status = await transport.put(
            ticket.url,
            file,
            ticket.headers,
            (loaded) => progress(file.size ? loaded / file.size : 1),
        );
        if (!ok(status)) throw new DirectUploadError('upload', status);
        confirmed = await transport.json(routes.confirm, {
            id: ticket.id,
            file_name: file.name,
        });
    } else if (ticket.method === 'POST') {
        const body = new FormData();
        body.append('id', ticket.id);
        body.append('file_name', file.name);
        body.append('file', file);
        confirmed = await transport.form(routes.confirm, body);
    } else {
        confirmed = await transport.json(routes.confirm, {
            id: ticket.id,
            file_name: file.name,
        });
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
