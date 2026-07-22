<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\User;
use App\Models\Vault;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Read-only credential lookup for the iOS Shortcut / widget and the in-page
 * autofill filler (see docs/ios-shortcut.md).
 *
 * Two token flavours, both minted via `php artisan vault:token`:
 *
 *  - device_token (X-Device-Token / Bearer): used by the Shortcut's native
 *    networking. Searches by the caller-supplied ?url= / ?q=.
 *
 *  - fill_token (`--fill`, passed as ?token=): embedded in a web page by the
 *    filler. It has higher exposure, so it is Origin-scoped — it ignores any
 *    caller-supplied query and only ever returns the credential for the
 *    request's real browser Origin, which page JS cannot forge. Rotate it
 *    independently of the device token.
 *
 * Reachable only over Tailscale (RequireLocalNetwork).
 */
class LookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->header('X-Device-Token')
            ?? $request->bearerToken()
            ?? $request->query('token');

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Missing token.'], 401);
        }

        $user = User::query()
            ->where('device_token', $token)
            ->orWhere('fill_token', $token)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        $viaFillToken = $user->fill_token !== null && hash_equals($user->fill_token, $token);

        if ($viaFillToken) {
            // In-page filler: scope strictly to the page the request came from.
            $origin = (string) $request->headers->get('Origin');
            $host = $origin !== '' ? parse_url($origin, PHP_URL_HOST) : null;

            if (! $host) {
                return response()->json(
                    ['message' => 'The fill token requires a browser Origin.'],
                    422
                );
            }

            $needle = $this->registrableDomain($host);
        } else {
            $query = trim((string) $request->query('url', $request->query('q', '')));

            if ($query === '') {
                return response()->json(['message' => 'Pass ?url= or ?q= to search.'], 422);
            }

            // A full URL is matched by host (www. stripped); anything else is a
            // plain name/url/username substring search.
            $host = parse_url(Str::startsWith($query, ['http://', 'https://']) ? $query : "https://{$query}", PHP_URL_HOST);
            $needle = $host ? preg_replace('/^www\./', '', strtolower($host)) : strtolower($query);
        }

        $includePasswords = (bool) config('vault.api_returns_passwords');

        $items = Item::query()
            ->whereIn('vault_id', Vault::forUser($user)->pluck('id'))
            ->where(function ($q) use ($needle) {
                $q->where('name', 'like', "%{$needle}%")
                    ->orWhere('url', 'like', "%{$needle}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (Item $item) => array_filter([
                'id' => $item->id,
                'name' => $item->name,
                'url' => $item->url,
                'username' => $item->username,
                'password' => $includePasswords ? $item->password : null,
                'totp' => $item->totp_secret !== null ? Totp::code($item->totp_secret) : null,
            ], fn ($value) => $value !== null));

        return response()
            ->json(['matches' => $items])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Reduce a host to its registrable domain (eTLD+1) so the filler matches
     * `accounts.google.com` against a stored `google.com`. Uses a short list of
     * common two-level public suffixes rather than the full Public Suffix List;
     * good enough for a household vault, and only affects the fill-token path.
     */
    private function registrableDomain(string $host): string
    {
        $host = preg_replace('/^www\./', '', strtolower($host));
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
}
