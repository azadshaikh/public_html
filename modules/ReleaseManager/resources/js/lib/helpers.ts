export function releaseRouteParams(
    type: string,
    params: Record<string, string | number | undefined> = {},
) {
    return {
        type,
        ...params,
    };
}
