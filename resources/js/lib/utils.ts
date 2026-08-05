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
 * A stored vault item URL with no scheme (e.g. "example.com") resolves as a
 * *relative* href — same-origin, so `target="_blank"` opens it inside the
 * installed PWA's own window instead of handing it to the browser. Force a
 * scheme so it's always treated as external.
 */
export function externalHref(url: string): string {
    return /^https?:\/\//i.test(url) ? url : `https://${url}`;
}
