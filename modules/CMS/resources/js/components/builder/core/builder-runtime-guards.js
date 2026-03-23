/**
 * Shared equality guards for high-frequency builder UI state.
 */

export function sameNodeIdList(left, right) {
    if (left === right) {
        return true;
    }

    if (left.length !== right.length) {
        return false;
    }

    for (let index = 0; index < left.length; index += 1) {
        if (left[index] !== right[index]) {
            return false;
        }
    }

    return true;
}

export function sameDropIndicator(left, right) {
    if (left === right) {
        return true;
    }

    if (!left || !right) {
        return false;
    }

    return left.targetId === right.targetId
        && left.position === right.position
        && left.isValid === right.isValid
        && left.parentId === right.parentId
        && left.index === right.index
        && left.rect.top === right.rect.top
        && left.rect.left === right.rect.left
        && left.rect.width === right.rect.width
        && left.rect.height === right.rect.height;
}
