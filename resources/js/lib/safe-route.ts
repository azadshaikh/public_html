/**
 * Safe wrapper around Ziggy's global `route()` function.
 *
 * When routes are filtered per-role, a component might reference a route name
 * that isn't present in the current user's Ziggy config. `safeRoute` catches
 * the resulting error and returns a fallback URL instead of crashing the app.
 *
 * Usage is identical to `route()` — it accepts the same arguments.
 *
 * @example
 *   safeRoute('app.users.index')             // "/admin/users" or "/404"
 *   safeRoute('app.users.show', { user: 1 }) // "/admin/users/1" or "/404"
 */
export function safeRoute(
    ...args: Parameters<typeof route>
): ReturnType<typeof route> {
    try {
        return route(...args);
    } catch (error) {
        if (import.meta.env.DEV) {
            console.warn(
                `[safeRoute] Route "${String(args[0])}" is not available for the current user.`,
                error,
            );
        }

        // When called with no arguments, route() returns a Router instance.
        // When called with a name, it returns a string URL.
        // Since the error only fires for named lookups, return a safe fallback.
        return '/404' as ReturnType<typeof route>;
    }
}

/**
 * Check whether a named route exists in the current Ziggy config.
 */
export function hasRoute(name: string): boolean {
    try {
        // Ziggy stores routes on the global Ziggy object.
        return (
            name in
            (
                window as unknown as {
                    Ziggy: { routes: Record<string, unknown> };
                }
            ).Ziggy.routes
        );
    } catch {
        return false;
    }
}
