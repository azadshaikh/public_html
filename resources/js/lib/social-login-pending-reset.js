export function registerSocialLoginPendingReset(target, resetPendingState) {
    const handlePageShow = () => {
        resetPendingState();
    };

    const handlePopState = () => {
        resetPendingState();
    };

    target.addEventListener('pageshow', handlePageShow);
    target.addEventListener('popstate', handlePopState);

    return () => {
        target.removeEventListener('pageshow', handlePageShow);
        target.removeEventListener('popstate', handlePopState);
    };
}
