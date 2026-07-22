function getCookie(name: string): string | null {
    const match = document.cookie.match(
        new RegExp('(^|; )' + name + '=([^;]*)'),
    );

    return match ? decodeURIComponent(match[2]) : null;
}

// Laravel accepts the (encrypted) XSRF-TOKEN cookie value in this header for
// same-origin, session-authenticated POST/DELETE requests made with fetch.
export function csrfHeaders(): Record<string, string> {
    const token = getCookie('XSRF-TOKEN');

    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
}
