export function shouldRenderDatagridHeading(showHeading, title, description) {
    if (showHeading !== true) {
        return false;
    }

    return Boolean(title || description);
}
