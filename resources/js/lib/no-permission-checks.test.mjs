import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import test from 'node:test';

// Guard 4: the client never decides what a viewer may do. It renders the action descriptors the server sends (absent, or disabled with a reason); a role or
// permission check in a page or component is a bug, however it is spelled.
const root = new URL('../', import.meta.url).pathname;
const banned = [/\brole\s*===/, /\bcan\(/, /\bhasRole\b/, /\bhasPermission\b/, /\bprops\.can\b/, /\bcan_[a-z]+\b/, /\bis_?admin\b/i, /\bpermissions\b\s*[.[]/];

function files(directory) {
    return readdirSync(directory).flatMap((name) => {
        const path = join(directory, name);
        if (statSync(path).isDirectory()) return name === 'node_modules' ? [] : files(path);
        return /\.(vue|ts)$/.test(name) && !name.endsWith('.d.ts') && !name.endsWith('.generated.d.ts') ? [path] : [];
    });
}

for (const file of files(root)) {
    test(`${file.slice(root.length)} makes no role or permission checks`, () => {
        const source = readFileSync(file, 'utf8');
        for (const pattern of banned) assert.doesNotMatch(source, pattern, `${pattern} in ${file}`);
    });
}
