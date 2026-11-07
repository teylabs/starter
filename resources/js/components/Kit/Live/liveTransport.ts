/**
 * Inertia live props over a host's Laravel Echo (docs/live-props.md).
 *
 * The kit never imports Inertia's `configureLive`, so a host on an Inertia client older than 3.11 still builds. A host on 3.11 or
 * later passes this transport to it: `configureLive(echoLiveTransport(() => echo))`. The Echo connection is lazy: `connect` runs
 * when the first page with a live prop subscribes. When it returns null (no broadcaster configured) or throws, every subscription
 * does nothing, and the page's fallback poll keeps the props fresh.
 */

/** A channel as Inertia names it in a page's `liveProps` (inertia-laravel 3.7 or later). */
export interface KitLiveChannel {
    name: string;
    type?: string;
}

/** The shape Inertia's `configureLive` takes (its `LiveTransport`), declared here so the kit compiles against older clients. */
export interface KitLiveTransport {
    subscribe(
        channel: KitLiveChannel,
        event: string,
        handler: (payload: unknown) => void,
    ): () => void;
    onStatusChange?(callback: (connected: boolean) => void): () => void;
}

/** The parts of a Laravel Echo channel the transport uses. */
export interface EchoChannelLike {
    listen(event: string, callback: (payload: unknown) => void): unknown;
    stopListening(
        event: string,
        callback?: (payload: unknown) => void,
    ): unknown;
}

/** The parts of Laravel Echo the transport uses. */
export interface EchoLike {
    channel(name: string): EchoChannelLike;
    private(name: string): EchoChannelLike;
    join?(name: string): EchoChannelLike;
    encryptedPrivate?(name: string): EchoChannelLike;
    leaveChannel(name: string): void;
}

export interface EchoLiveOptions {
    /** Only these broadcast names are subscribed; any other event a page lists is ignored. Omit to take every event. */
    events?: readonly string[];
    /** The name handed to Echo's `listen`. The default prefixes a dot, so Echo uses the broadcast name as is, without its namespace. */
    eventName?: (event: string) => string;
    /** Reports the socket's connection state, so Inertia refreshes every live prop when a dropped connection comes back. */
    onStatusChange?: (callback: (connected: boolean) => void) => () => void;
}

/** Echo's own channel name for each channel type, as `leaveChannel` takes it. */
const PREFIXES: Record<string, string> = {
    public: '',
    private: 'private-',
    presence: 'presence-',
    'encrypted-private': 'private-encrypted-',
};

function open(echo: EchoLike, channel: KitLiveChannel): EchoChannelLike | null {
    switch (channel.type ?? 'public') {
        case 'public':
            return echo.channel(channel.name);
        case 'private':
            return echo.private(channel.name);
        case 'presence':
            return echo.join?.(channel.name) ?? null;
        case 'encrypted-private':
            return echo.encryptedPrivate?.(channel.name) ?? null;
        default:
            // An unknown type fails closed: no listener, rather than a guess at a public channel.
            return null;
    }
}

/**
 * How a host's own realtime connection listens: start listening for `event` on `channel` and return the function that stops it. A
 * host whose connector already shares channels (and reference-counts them) plugs in here; the kit adds the laziness, the allow-list
 * and the stop-before-connect guard.
 */
export type LiveListen = (
    channel: KitLiveChannel,
    event: string,
    handler: (payload: unknown) => void,
) => () => void;

export interface ListenerLiveOptions {
    /** Only these broadcast names are subscribed; any other event a page lists is ignored. Omit to take every event. */
    events?: readonly string[];
    /** Reports the socket's connection state, so Inertia refreshes every live prop when a dropped connection comes back. */
    onStatusChange?: (callback: (connected: boolean) => void) => () => void;
}

/**
 * A transport for `configureLive` over the host's own connection. `connect` runs once, when the first page with a live prop
 * subscribes; null (no broadcaster) or a throw leaves every subscription doing nothing, so the page's fallback poll carries it.
 */
export function listenerLiveTransport(
    connect: () => LiveListen | null | Promise<LiveListen | null>,
    options: ListenerLiveOptions = {},
): KitLiveTransport {
    let connection: Promise<LiveListen | null> | null = null;
    const listener = (): Promise<LiveListen | null> =>
        (connection ??= Promise.resolve()
            .then(connect)
            .catch(() => null));

    return {
        subscribe(channel, event, handler) {
            if (
                options.events !== undefined &&
                !options.events.includes(event)
            ) {
                return () => {};
            }
            let stopped = false;
            let stop: (() => void) | null = null;

            void listener().then((listen) => {
                if (listen !== null && !stopped) {
                    stop = listen(channel, event, handler);
                }
            });

            return () => {
                if (stopped) {
                    return;
                }
                stopped = true;
                stop?.();
            };
        },
        ...(options.onStatusChange === undefined
            ? {}
            : { onStatusChange: options.onStatusChange }),
    };
}

/** Echo's `listen`, sharing each channel between its listeners and leaving it when the last one goes. */
function echoListen(
    echo: EchoLike,
    eventName: (event: string) => string,
): LiveListen {
    const channels = new Map<
        string,
        { channel: EchoChannelLike; users: number }
    >();

    return (channel, event, handler) => {
        const type = channel.type ?? 'public';
        const key = `${type}:${channel.name}`;
        let entry = channels.get(key);
        if (entry === undefined) {
            const opened = open(echo, channel);
            if (opened === null) {
                return () => {};
            }
            entry = { channel: opened, users: 0 };
            channels.set(key, entry);
        }
        const held = entry;
        const listened = eventName(event);
        const callback = (payload: unknown) => handler(payload);
        held.users++;
        held.channel.listen(listened, callback);

        return () => {
            held.channel.stopListening(listened, callback);
            held.users--;
            // The channel is left only when its last listener goes, so two live props on one channel stay independent.
            if (held.users === 0) {
                channels.delete(key);
                echo.leaveChannel(`${PREFIXES[type]}${channel.name}`);
            }
        };
    };
}

/** A transport over Laravel Echo: `listenerLiveTransport` with Echo's channels, shared and left on the last stop. */
export function echoLiveTransport(
    connect: () => EchoLike | null | Promise<EchoLike | null>,
    options: EchoLiveOptions = {},
): KitLiveTransport {
    const eventName =
        options.eventName ??
        ((event: string) => (event.startsWith('.') ? event : `.${event}`));

    return listenerLiveTransport(
        () =>
            Promise.resolve()
                .then(connect)
                .then((echo) =>
                    echo === null ? null : echoListen(echo, eventName),
                ),
        options,
    );
}
