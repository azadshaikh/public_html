export const contentStore = {
    original: {},
    modified: {},
};

export const modelStore = {
    models: {},
};

export function disposeModel(path) {
    const model = modelStore.models[path];
    if (!model) return;

    try {
        if (!model.isDisposed?.()) {
            model.dispose();
        }
    } catch (_) {}

    delete modelStore.models[path];
}
