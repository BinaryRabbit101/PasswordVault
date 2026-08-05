import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Auto-lock behavior for secret-bearing screens:
 * - the moment the app is backgrounded, `onHide` runs (drop revealed secrets
 *   from memory — the caller decides what that means, e.g. re-masking a
 *   still-open sheet rather than closing it);
 * - coming back runs `onShow` (e.g. re-fetch whatever `onHide` dropped);
 * - returning after more than `idleMinutes` additionally reloads the page so
 *   stale props are refetched and an expired session bounces to login.
 */
export function useAutoLock(
    onHide: () => void,
    idleMinutes: number = 5,
    onShow?: () => void,
) {
    let hiddenAt: number | null = null;

    const handler = () => {
        if (document.visibilityState === 'hidden') {
            hiddenAt = Date.now();
            onHide();

            return;
        }

        onShow?.();

        if (hiddenAt !== null && Date.now() - hiddenAt > idleMinutes * 60_000) {
            hiddenAt = null;
            router.reload();
        }
    };

    onMounted(() => document.addEventListener('visibilitychange', handler));
    onBeforeUnmount(() =>
        document.removeEventListener('visibilitychange', handler),
    );
}
