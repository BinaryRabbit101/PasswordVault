import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

/**
 * Web Push subscription helper (ported from LittlePocketMeseum).
 *
 * Handles asking the user for permission, subscribing to the browser's push
 * service using our VAPID public key, and syncing that subscription with the
 * Laravel backend (`push.subscribe` / `push.unsubscribe`).
 *
 * Requires a secure context (HTTPS or localhost) and the service worker
 * registered at `/serviceworker.js` (see app.ts).
 */

// Web Push needs the VAPID public key as a Uint8Array, not base64.
function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const output = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        output[i] = rawData.charCodeAt(i);
    }
    return output;
}

function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : null;
}

// Laravel accepts the (encrypted) XSRF-TOKEN cookie value in this header.
function csrfHeaders(): Record<string, string> {
    const token = getCookie('XSRF-TOKEN');
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
}

export function usePush() {
    const page = usePage();

    // Feature support: needs SW + Push API + Notifications, all in a secure context.
    const isSupported = ref(
        typeof window !== 'undefined' &&
            'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window,
    );

    const isSubscribed = ref(false);
    const permission = ref<NotificationPermission>(
        isSupported.value ? Notification.permission : 'denied',
    );
    const isBusy = ref(false);
    const error = ref<string | null>(null);

    async function getRegistration(): Promise<ServiceWorkerRegistration> {
        return navigator.serviceWorker.ready;
    }

    async function refresh() {
        if (!isSupported.value) return;
        try {
            const reg = await getRegistration();
            const sub = await reg.pushManager.getSubscription();
            isSubscribed.value = !!sub;
            permission.value = Notification.permission;
        } catch {
            // Non-fatal: leave state as-is.
        }
    }

    async function subscribe(): Promise<boolean> {
        error.value = null;

        if (!isSupported.value) {
            error.value = 'Push notifications are not supported in this browser.';
            return false;
        }

        const vapidPublicKey = page.props.vapidPublicKey as string | null;
        if (!vapidPublicKey) {
            error.value = 'Server is missing a VAPID public key.';
            return false;
        }

        isBusy.value = true;
        try {
            permission.value = await Notification.requestPermission();
            if (permission.value !== 'granted') {
                error.value = 'Notification permission was not granted.';
                return false;
            }

            const reg = await getRegistration();
            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey) as BufferSource,
                });
            }

            // Record the payload encoding to store server-side, PREFERRING the
            // modern 'aes128gcm'. Some browsers (iOS Safari) advertise
            // ['aesgcm', 'aes128gcm'] with the deprecated one first, so we must
            // pick aes128gcm explicitly rather than just taking index 0.
            const supportedEncodings =
                (PushManager as unknown as { supportedContentEncodings?: string[] })
                    .supportedContentEncodings ?? [];
            const contentEncoding = supportedEncodings.includes('aes128gcm')
                ? 'aes128gcm'
                : (supportedEncodings[0] ?? 'aes128gcm');

            const res = await fetch('/push/subscribe', {
                method: 'POST',
                headers: csrfHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ ...sub.toJSON(), contentEncoding }),
            });

            if (!res.ok) {
                error.value = 'Could not save the subscription on the server.';
                return false;
            }

            isSubscribed.value = true;
            return true;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to enable notifications.';
            return false;
        } finally {
            isBusy.value = false;
        }
    }

    async function unsubscribe(): Promise<boolean> {
        error.value = null;
        if (!isSupported.value) return false;

        isBusy.value = true;
        try {
            const reg = await getRegistration();
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                await fetch('/push/subscribe', {
                    method: 'DELETE',
                    headers: csrfHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({ endpoint: sub.endpoint }),
                });
                await sub.unsubscribe();
            }
            isSubscribed.value = false;
            return true;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to disable notifications.';
            return false;
        } finally {
            isBusy.value = false;
        }
    }

    onMounted(refresh);

    return { isSupported, isSubscribed, permission, isBusy, error, subscribe, unsubscribe, refresh };
}
