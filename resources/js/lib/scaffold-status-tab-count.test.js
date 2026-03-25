import assert from 'node:assert/strict';
import test from 'node:test';
import { resolveScaffoldStatusTabCount } from './scaffold-status-tab-count.js';

test('resolveScaffoldStatusTabCount returns the total for the all tab', () => {
    assert.equal(
        resolveScaffoldStatusTabCount('all', {
            total: 12,
            active: 7,
            trash: 1,
        }),
        12,
    );
});

test('resolveScaffoldStatusTabCount returns the matching status count for non-all tabs', () => {
    assert.equal(
        resolveScaffoldStatusTabCount('trash', {
            total: 12,
            trash: 1,
        }),
        1,
    );
});

test('resolveScaffoldStatusTabCount falls back to zero when a count is missing', () => {
    assert.equal(resolveScaffoldStatusTabCount('expired', { total: 12 }), 0);
});
