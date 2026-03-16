/**
 * State Manager
 *
 * Non-reactive storage for file content and Monaco models.
 * These are plain JS objects, not Alpine reactive proxies, to prevent
 * performance issues with large content changes.
 */

// Non-reactive content storage
const _contentStore = {
    original: {},
    modified: {},
};

// Monaco models per file (non-reactive)
const _modelStore = {
    models: {},
};

// Content Store API
export function getOriginalContent(path) {
    return _contentStore.original[path];
}

export function getModifiedContent(path) {
    return _contentStore.modified[path];
}

export function setOriginalContent(path, content) {
    _contentStore.original[path] = content;
}

export function setModifiedContent(path, content) {
    _contentStore.modified[path] = content;
}

export function deleteContent(path) {
    delete _contentStore.original[path];
    delete _contentStore.modified[path];
}

export function isDirty(path) {
    return _contentStore.original[path] !== _contentStore.modified[path];
}

export function hasUnsavedChanges() {
    return Object.keys(_contentStore.original).some((path) => isDirty(path));
}

export function getAllDirtyContent() {
    const dirtyContent = {};
    Object.keys(_contentStore.original).forEach((path) => {
        if (isDirty(path)) {
            dirtyContent[path] = _contentStore.modified[path];
        }
    });
    return dirtyContent;
}

// Model Store API
export function getModel(path) {
    return _modelStore.models[path];
}

export function setModel(path, model) {
    _modelStore.models[path] = model;
}

export function deleteModel(path) {
    const model = _modelStore.models[path];
    if (model) {
        try {
            if (!model.isDisposed?.()) {
                model.dispose();
            }
        } catch (e) {
            console.warn('Error disposing model:', e);
        }
        delete _modelStore.models[path];
    }
}

export function hasModel(path) {
    return !!_modelStore.models[path];
}
