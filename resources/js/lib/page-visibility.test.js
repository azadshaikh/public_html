import assert from 'node:assert/strict';
import test from 'node:test';
import { isPageVisible } from './page-visibility.js';

test('isPageVisible only pauses polling for hidden pages', () => {
    assert.equal(isPageVisible('visible'), true);
    assert.equal(isPageVisible('prerender'), true);
    assert.equal(isPageVisible('hidden'), false);
});
