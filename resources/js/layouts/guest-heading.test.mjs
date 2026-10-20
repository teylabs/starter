import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

// The guest card heading is Laravel's Vue starter kit's AuthLayout heading, in the text face: exactly this class.
const heading = 'text-xl font-medium';

for (const file of ['GuestLayout.vue', '../pages/Auth/SignIn/Page.vue']) {
    test(`the guest card heading in ${file} is ${heading}`, () => {
        const source = readFileSync(new URL(file, import.meta.url), 'utf8');
        const classes = [...source.matchAll(/<h1\b[^>]*\bclass="([^"]*)"/g)].map((match) => match[1]);
        assert.deepEqual(classes, [heading]);
    });
}
