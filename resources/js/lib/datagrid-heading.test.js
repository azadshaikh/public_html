import assert from 'node:assert/strict';
import test from 'node:test';
import { shouldRenderDatagridHeading } from './datagrid-heading.js';

test('shouldRenderDatagridHeading stays off by default even when content is provided', () => {
    assert.equal(
        shouldRenderDatagridHeading(
            undefined,
            'Websites',
            'Manage your websites and hosting.',
        ),
        false,
    );
});

test('shouldRenderDatagridHeading returns true when explicitly enabled and content exists', () => {
    assert.equal(shouldRenderDatagridHeading(true, 'Websites', ''), true);
});

test('shouldRenderDatagridHeading returns false when enabled without heading content', () => {
    assert.equal(shouldRenderDatagridHeading(true, '', ''), false);
});
