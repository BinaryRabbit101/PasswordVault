import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Auto-lock behavior for secret-bearing screens:
 * - the moment the app is backgrounded, `onHide` runs (close sheets, drop
 *   revealed secrets from memory);
 * - returning after more than `idleMinutes` reloads the page so stale props
 *   are refetched and an expired session bounces to login.
 */
export function useAutoLock(onHide: () => void, idleMinutes: number = 5) {
    let hiddenAt: number | null = null;

    const handler = () => {
        if (document.visibilityState === 'hidden') {
            hiddenAt = Date.now();
            onHide();
        } else if (
            hiddenAt !== null &&
            Date.now() - hiddenAt > idleMinutes * 60_000
        ) {
            hiddenAt = null;
            router.reload();
        }
    };

    onMounted(() => document.addEventListener('visibilitychange', handler));
    onBeforeUnmount(() =>
        document.removeEventListener('visibilitychange', handler),
    );
}
