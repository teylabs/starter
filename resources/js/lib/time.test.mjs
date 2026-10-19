import assert from 'node:assert/strict';
import test from 'node:test';
import { localTime } from './time.ts';

const hour = { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', locale: 'en-CA' };

test('converts a UTC instant to the given zone', () => {
    assert.equal(localTime('2026-07-01T16:00:00Z', { ...hour, zone: 'America/Toronto' }), '12:00');
    assert.equal(localTime('2026-01-01T16:00:00+00:00', { ...hour, zone: 'Asia/Kolkata' }), '21:30');
});

test('defaults to the viewer zone and a medium date with a short time', () => {
    const expected = new Intl.DateTimeFormat('en-CA', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date('2026-07-01T16:00:00Z'));
    assert.equal(localTime('2026-07-01T16:00:00Z', { locale: 'en-CA' }), expected);
});

test('refuses a timestamp without an offset', () => {
    assert.throws(() => localTime('2026-07-01 16:00:00'), RangeError);
    assert.throws(() => localTime('not a time Z'), RangeError);
});
