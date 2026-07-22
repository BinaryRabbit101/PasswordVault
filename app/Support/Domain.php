<?php

namespace App\Support;

class Domain
{
    /**
     * Reduce a host to its registrable domain (eTLD+1) so a login subdomain
     * (accounts.google.com) matches a stored bare domain (google.com). Uses a
     * short list of common two-level public suffixes rather than the full
     * Public Suffix List — good enough for a household vault.
     */
    public static function registrable(string $host): string
    {
        $host = preg_replace('/^www\./', '', strtolower(trim($host)));
        $labels = explode('.', $host);

        if (count($labels) <= 2) {
            return $host;
        }

        $twoLevel = [
            'co.uk', 'org.uk', 'gov.uk', 'ac.uk', 'me.uk',
            'co.jp', 'com.au', 'net.au', 'org.au', 'co.nz',
            'co.za', 'com.br', 'com.mx', 'co.in', 'com.sg',
        ];

        $lastTwo = implode('.', array_slice($labels, -2));

        return in_array($lastTwo, $twoLevel, true)
            ? implode('.', array_slice($labels, -3))
            : $lastTwo;
    }

    /**
     * Registrable domain parsed from a full or bare URL string, or null when
     * there is no usable host.
     */
    public static function fromUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        $absolute = str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : "https://{$url}";

        $host = parse_url($absolute, PHP_URL_HOST);

        return $host ? self::registrable($host) : null;
    }
}
