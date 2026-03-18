export function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

export async function jsonRequest(url, options = {}) {
    const {
        method = 'GET',
        data,
        headers = {},
        credentials = 'same-origin',
    } = options;

    const response = await fetch(url, {
        method,
        credentials,
        headers: {
            Accept: 'application/json',
            ...(data !== undefined ? { 'Content-Type': 'application/json' } : {}),
            ...(getCsrfToken() ? { 'X-CSRF-TOKEN': getCsrfToken() } : {}),
            ...headers,
        },
        ...(data !== undefined ? { body: JSON.stringify(data) } : {}),
    });

    const responseData = await response.json();

    if (!response.ok) {
        const error = new Error(responseData.message || 'Server error');
        error.status = response.status;
        error.errors = responseData.errors;
        error.response = responseData;
        throw error;
    }

    return responseData;
}
