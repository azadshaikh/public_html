/**
 * Debug Logger
 *
 * Conditional console logging that only outputs when window.Astero.debug is true.
 * This allows debug logs to be hidden in production while keeping them available for development.
 */

export function debugLog(...args) {
    if (window.Astero?.debug) {
        console.log(...args);
    }
}

export function debugWarn(...args) {
    if (window.Astero?.debug) {
        console.warn(...args);
    }
}

export function debugError(...args) {
    // Always log errors, but prefix with debug indicator when not in debug mode
    console.error(...args);
}
