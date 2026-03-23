import assert from 'node:assert/strict';
import test from 'node:test';
import { getDefaultDatagridCardColumnCount } from './datagrid-card-columns.js';

test('getDefaultDatagridCardColumnCount matches default Tailwind breakpoints', () => {
    assert.equal(getDefaultDatagridCardColumnCount(320), 1);
    assert.equal(getDefaultDatagridCardColumnCount(767), 1);
    assert.equal(getDefaultDatagridCardColumnCount(768), 2);
    assert.equal(getDefaultDatagridCardColumnCount(1279), 2);
    assert.equal(getDefaultDatagridCardColumnCount(1280), 3);
});
