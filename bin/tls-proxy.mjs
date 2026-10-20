// A local TLS-terminating proxy, as a hosting platform puts in front of the app: node bin/tls-proxy.mjs <port> <app port> <key> <cert>.
import { readFileSync } from 'node:fs';
import { request } from 'node:http';
import { createServer } from 'node:https';

const [port, target, key, cert] = process.argv.slice(2);
createServer({ key: readFileSync(key), cert: readFileSync(cert) }, (incoming, outgoing) => {
    const headers = { ...incoming.headers, 'x-forwarded-proto': 'https', 'x-forwarded-host': incoming.headers.host ?? '', 'x-forwarded-port': port, 'x-forwarded-for': incoming.socket.remoteAddress ?? '' };
    const upstream = request({ host: '127.0.0.1', port: Number(target), path: incoming.url, method: incoming.method, headers }, (response) => {
        outgoing.writeHead(response.statusCode ?? 502, response.headers);
        response.pipe(outgoing);
    });
    upstream.on('error', () => { outgoing.writeHead(502); outgoing.end(); });
    incoming.pipe(upstream);
}).listen(Number(port), () => console.log(`TLS proxy on ${port} -> ${target}`));
