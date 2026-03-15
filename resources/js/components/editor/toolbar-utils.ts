export function flattenToolbar<T extends string>(toolbar: Array<T | T[]>): T[] {
    const actions: T[] = [];

    for (const item of toolbar) {
        if (Array.isArray(item)) {
            item.forEach((action) => actions.push(action));
            continue;
        }

        actions.push(item);
    }

    return actions;
}

export function mapActionsToPlugins<
    TAction extends string,
    TPlugin,
>(
    actions: TAction[],
    pluginMap: Partial<Record<TAction, TPlugin>>,
    warnOnMissing = false,
): TPlugin[] {
    const plugins: TPlugin[] = [];

    for (const action of actions) {
        const plugin = pluginMap[action];

        if (plugin) {
            plugins.push(plugin);
            continue;
        }

        if (warnOnMissing && typeof console !== 'undefined' && console.warn) {
            console.warn(
                `[AsteroNote] Unknown toolbar action "${action}" - no matching plugin found.`,
            );
        }
    }

    return plugins;
}

export function resolvePlugins<
    TAction extends string,
    TPlugin,
>(
    toolbar: Array<TAction | TAction[]>,
    pluginMap: Partial<Record<TAction, TPlugin>>,
    warnOnMissing = false,
): TPlugin[] {
    return mapActionsToPlugins(
        flattenToolbar(toolbar),
        pluginMap,
        warnOnMissing,
    );
}
