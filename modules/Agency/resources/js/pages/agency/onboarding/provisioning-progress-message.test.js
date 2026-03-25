import assert from 'node:assert/strict';
import test from 'node:test';
import { getProvisioningProgressMessage } from './provisioning-progress-message.js';

test('getProvisioningProgressMessage returns completed step copy when progress exists', () => {
    assert.equal(
        getProvisioningProgressMessage({
            completed_steps: 2,
            total_steps: 5,
        }),
        '2 of 5 setup steps completed',
    );
});

test('getProvisioningProgressMessage hides the secondary copy when no steps are completed yet', () => {
    assert.equal(
        getProvisioningProgressMessage({
            completed_steps: 0,
            total_steps: 5,
        }),
        null,
    );
});
