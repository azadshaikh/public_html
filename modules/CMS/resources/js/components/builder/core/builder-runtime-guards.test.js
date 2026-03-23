import assert from 'node:assert/strict';
import test from 'node:test';
import { sameDropIndicator, sameNodeIdList } from './builder-runtime-guards.js';

test('sameNodeIdList returns true only for identical ordered lists', () => {
    assert.equal(sameNodeIdList(['node-1', 'node-2'], ['node-1', 'node-2']), true);
    assert.equal(sameNodeIdList(['node-1', 'node-2'], ['node-2', 'node-1']), false);
    assert.equal(sameNodeIdList(['node-1'], ['node-1', 'node-2']), false);
});

test('sameDropIndicator compares semantic drop state and rect values', () => {
    const baseIndicator = {
        targetId: 'node-2',
        position: 'inside',
        rect: { top: 10, left: 20, width: 300, height: 4 },
        isValid: true,
        parentId: 'node-1',
        index: 2,
    };

    assert.equal(sameDropIndicator(baseIndicator, { ...baseIndicator }), true);
    assert.equal(
        sameDropIndicator(baseIndicator, {
            ...baseIndicator,
            rect: { ...baseIndicator.rect, top: 11 },
        }),
        false,
    );
    assert.equal(sameDropIndicator(baseIndicator, null), false);
    assert.equal(sameDropIndicator(null, null), true);
});
