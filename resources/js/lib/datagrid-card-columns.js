export function getDefaultDatagridCardColumnCount(viewportWidth) {
    if (viewportWidth >= 1280) {
        return 3;
    }

    if (viewportWidth >= 768) {
        return 2;
    }

    return 1;
}
