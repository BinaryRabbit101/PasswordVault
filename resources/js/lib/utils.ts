import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/**
 * True only inside an iOS home-screen "standalone" install — `standalone` is
 * a long-standing Safari-only `navigator` extension with no DOM typing, and
 * no equivalent on Android/desktop (they use `display-mode: standalone`
 * media queries instead, which don't have this same-origin-sheet problem).
 */
function isIosStandalone(): boolean {
    return (
        typeof navigator !== 'undefined' &&
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

/**
 * A stored vault item URL with no scheme (e.g. "example.com") resolves as a
 * *relative* href — same-origin, so `target="_blank"` opens it inside the
 * installed PWA's own window instead of handing it to the browser. Force a
 * scheme so it's always treated as external.
 *
 * From an iOS home-screen install, even a correctly-scheme'd external link
 * only opens an embedded in-app Safari sheet — iOS intercepts the
 * navigation rather than launching the real Safari app. Prefixing the
 * scheme with `x-safari-` hands it to Safari directly instead. This is an
 * undocumented, Apple-unsupported scheme handoff; it works on current iOS
 * but could silently stop working in a future release.
 */
export function externalHref(url: string): string {
    const normalized = /^https?:\/\//i.test(url) ? url : `https://${url}`;

    if (!isIosStandalone()) {
        return normalized;
    }

    return normalized.replace(
        /^https?:\/\//i,
        (scheme) => `x-safari-${scheme}`,
    );
}
