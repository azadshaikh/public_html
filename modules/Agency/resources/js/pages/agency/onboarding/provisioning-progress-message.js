export function getProvisioningProgressMessage(progress) {
    if (progress.completed_steps > 0 && progress.total_steps > 0) {
        return `${progress.completed_steps} of ${progress.total_steps} setup steps completed`;
    }

    return null;
}
