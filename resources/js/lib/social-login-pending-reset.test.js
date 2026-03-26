import assert from 'node:assert/strict';
import test from 'node:test';
import { registerSocialLoginPendingReset } from './social-login-pending-reset.js';

function createEventTarget() {
    const listeners = new Map();

    return {
        addEventListener(type, listener) {
            listeners.set(type, listener);
        },
        removeEventListener(type, listener) {
            if (listeners.get(type) === listener) {
                listeners.delete(type);
            }
        },
        dispatch(type) {
            const listener = listeners.get(type);

            if (listener) {
                listener();
            }
        },
        has(type) {
            return listeners.has(type);
        },
    };
}

test('registerSocialLoginPendingReset resets pending state on browser restore events', () => {
    const target = createEventTarget();
    let resetCount = 0;

    const cleanup = registerSocialLoginPendingReset(target, () => {
        resetCount += 1;
    });

    assert.equal(target.has('pageshow'), true);
    assert.equal(target.has('popstate'), true);

    target.dispatch('pageshow');
    target.dispatch('popstate');

    assert.equal(resetCount, 2);

    cleanup();

    assert.equal(target.has('pageshow'), false);
    assert.equal(target.has('popstate'), false);
});
