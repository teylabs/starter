import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

// The guest card heading is the kit's auth heading (its 0.51 Refactoring UI pass), in the display face: exactly this class.
const heading = 'font-display text-2xl font-semibold tracking-tight';

for (const file of ['GuestLayout.vue', '../pages/Auth/Login/Page.vue']) {
    test(`the guest card heading in ${file} is ${heading}`, () => {
        const source = readFileSync(new URL(file, import.meta.url), 'utf8');
        const classes = [
            ...source.matchAll(/<h1\b[^>]*\bclass="([^"]*)"/g),
        ].map((match) => match[1]);
        assert.deepEqual(classes, [heading]);
    });
}
