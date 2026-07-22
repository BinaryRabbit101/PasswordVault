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
 * Read-only credential lookup for the iOS Shortcut / Scriptable widget.
 *
 * Authenticated with a per-device token (X-Device-Token header or Bearer
 * token) minted via `php artisan vault:token`. Rotating the token revokes a
 * device. Reachable only over Tailscale (RequireLocalNetwork).
 */
class LookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->header('X-Device-Token') ?? $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Missing device token.'], 401);
        }

        $user = User::where('device_token', $token)->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid device token.'], 401);
        }

        $query = trim((string) $request->query('url', $request->query('q', '')));

        if ($query === '') {
            return response()->json(['message' => 'Pass ?url= or ?q= to search.'], 422);
        }

        // A full URL is matched by host (www. stripped); anything else is a
        // plain name/url/username substring search.
        $host = parse_url(Str::startsWith($query, ['http://', 'https://']) ? $query : "https://{$query}", PHP_URL_HOST);
        $needle = $host ? preg_replace('/^www\./', '', strtolower($host)) : strtolower($query);

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
}
