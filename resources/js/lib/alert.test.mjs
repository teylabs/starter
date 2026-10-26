import assert from 'node:assert/strict';
import test from 'node:test';
import { alertIconClass, alertVariants } from '../components/Kit/Feedback/alertVariants.ts';

const variants = ['info', 'success', 'warning', 'danger', 'primary', 'brand', 'muted'];
const tiers = ['pale', 'solid'];

for (const variant of variants) {
    for (const tier of tiers) {
        test(`the ${tier} ${variant} alert gets its colours from the shared variants`, () => {
            const classes = alertVariants({ variant, tier });
            assert.match(classes, /\bbg-/, 'a surface');
            assert.match(classes, /\btext-/, 'a text colour');
            assert.doesNotMatch(classes, /#[0-9a-f]{3,8}|\[#|rgb\(/i, 'no literal colours: they come from the palette and theme tokens');
            assert.notEqual(alertIconClass(variant, tier), '');
        });
    }
}

test('the semantic pale variants have a dark-mode pairing, and solid ones are white on a deep colour', () => {
    for (const variant of ['info', 'success', 'warning', 'danger']) {
        assert.match(alertVariants({ variant, tier: 'pale' }), /dark:bg-/);
        assert.match(alertVariants({ variant, tier: 'solid' }), /text-white/);
    }
});

test('the default is the pale info alert', () => {
    assert.equal(alertVariants({}), alertVariants({ variant: 'info', tier: 'pale' }));
});
