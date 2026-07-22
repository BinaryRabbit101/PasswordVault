import { toast } from 'vue-sonner';

let clearTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * Copy secrets to the clipboard with a best-effort auto-clear.
 *
 * Accepts either a plain value or a promise producing one. The promise form
 * uses ClipboardItem so Safari accepts the write: the ClipboardItem must be
 * constructed synchronously inside the tap gesture, with the async value
 * resolving afterwards.
 */
export function useClipboard(clearSeconds: number = 30) {
    const scheduleClear = (label: string) => {
        if (clearTimer) {
            clearTimeout(clearTimer);
        }

        if (clearSeconds > 0) {
            clearTimer = setTimeout(() => {
                // iOS refuses clipboard writes without a user gesture; treat
                // the clear as best effort and ignore rejections.
                navigator.clipboard.writeText('').catch(() => {});
            }, clearSeconds * 1000);
        }

        toast.success(
            clearSeconds > 0
                ? `${label} copied — clears in ${clearSeconds}s`
                : `${label} copied`,
        );
    };

    const copy = async (label: string, value: string | Promise<string>) => {
        try {
            if (typeof value === 'string') {
                await navigator.clipboard.writeText(value);
            } else if (typeof ClipboardItem !== 'undefined') {
                await navigator.clipboard.write([
                    new ClipboardItem({
                        'text/plain': value.then(
                            (text) => new Blob([text], { type: 'text/plain' }),
                        ),
                    }),
                ]);
            } else {
                await navigator.clipboard.writeText(await value);
            }

            scheduleClear(label);
        } catch {
            toast.error(`Could not copy ${label.toLowerCase()}.`);
        }
    };

    return { copy };
}
