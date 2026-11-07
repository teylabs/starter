// The passkey offer's browser side, on Fortify's own registration endpoints: fetch the creation options, let the browser create the credential, send it back.
// It uses the browser's own WebAuthn JSON helpers (PublicKeyCredential.parseCreationOptionsFromJSON and credential.toJSON()), so no client library is needed;
// a browser without them isn't offered a passkey at all.

import { responseJson, type HttpBody } from '../http';

type Credentials = {
    create: (options: {
        publicKey: unknown;
    }) => Promise<{ toJSON: () => unknown } | null>;
};
type PublicKeyCredentialStatic = {
    parseCreationOptionsFromJSON?: (json: unknown) => unknown;
};
type Http = {
    request: (config: {
        method: string;
        url: string;
        data?: unknown;
        headers?: Record<string, string>;
    }) => Promise<HttpBody>;
};

export function passkeysSupported(
    scope: {
        PublicKeyCredential?: PublicKeyCredentialStatic;
        navigator?: { credentials?: Partial<Credentials> };
    } = globalThis as never,
): boolean {
    return (
        typeof scope.PublicKeyCredential?.parseCreationOptionsFromJSON ===
            'function' &&
        typeof scope.navigator?.credentials?.create === 'function'
    );
}

/** A name for the passkey, so the person can tell their passkeys apart later: the device's platform, else "Passkey". */
export function passkeyName(platform: string | undefined): string {
    const name = (platform ?? '').trim();
    return name ? `${name} passkey` : 'Passkey';
}

/** Registers one passkey: options from `optionsUrl`, the browser's create(), then the credential to `storeUrl`. Throws when the person cancels or it fails. */
export async function registerPasskey(
    http: Http,
    urls: { options_url: string; store_url: string },
    name: string,
    scope: {
        PublicKeyCredential: Required<PublicKeyCredentialStatic>;
        navigator: { credentials: Credentials };
    } = globalThis as never,
): Promise<void> {
    const response = await http.request({
        method: 'get',
        url: urls.options_url,
        headers: { Accept: 'application/json' },
    });
    // Inertia v3's client gives the body as a string: responseJson parses it, and throws on a non-JSON answer rather than passing on undefined options.
    const options = (responseJson(response) as { options?: unknown } | null)
        ?.options;
    const credential = await scope.navigator.credentials.create({
        publicKey:
            scope.PublicKeyCredential.parseCreationOptionsFromJSON(options),
    });
    if (!credential) throw new Error('No passkey was created.');
    await http.request({
        method: 'post',
        url: urls.store_url,
        data: { name, credential: credential.toJSON() },
        headers: { Accept: 'application/json' },
    });
}

type RequestCredentials = {
    get: (options: {
        publicKey: unknown;
    }) => Promise<{ toJSON: () => unknown } | null>;
};
type PublicKeyRequestStatic = {
    parseRequestOptionsFromJSON?: (json: unknown) => unknown;
};

/** Whether this browser can sign in with a passkey (the WebAuthn JSON helpers, and credentials.get). */
export function passkeySignInSupported(
    scope: {
        PublicKeyCredential?: PublicKeyRequestStatic;
        navigator?: { credentials?: Partial<RequestCredentials> };
    } = globalThis as never,
): boolean {
    return (
        typeof scope.PublicKeyCredential?.parseRequestOptionsFromJSON ===
            'function' &&
        typeof scope.navigator?.credentials?.get === 'function'
    );
}

/**
 * Signs in with a passkey, on Fortify's endpoints: options from `options_url`, the browser's get(), then the credential to `login_url`. Returns where to go next
 * (the server's intended URL). Throws when the person cancels, or the server refuses the passkey (its message, where it gives one).
 */
export async function signInWithPasskey(
    http: Http,
    urls: { options_url: string; login_url: string },
    remember: boolean,
    scope: {
        PublicKeyCredential: Required<PublicKeyRequestStatic>;
        navigator: { credentials: RequestCredentials };
    } = globalThis as never,
): Promise<string> {
    const response = await http.request({
        method: 'get',
        url: urls.options_url,
        headers: { Accept: 'application/json' },
    });
    const options = (responseJson(response) as { options?: unknown } | null)
        ?.options;
    const credential = await scope.navigator.credentials.get({
        publicKey:
            scope.PublicKeyCredential.parseRequestOptionsFromJSON(options),
    });
    if (!credential) throw new Error('No passkey was chosen.');
    const answer = await http.request({
        method: 'post',
        url: urls.login_url,
        data: { credential: credential.toJSON(), remember },
        headers: { Accept: 'application/json' },
    });
    const redirect = (responseJson(answer) as { redirect?: unknown } | null)
        ?.redirect;
    return typeof redirect === 'string' && redirect !== '' ? redirect : '/';
}
