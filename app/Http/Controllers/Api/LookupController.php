<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\User;
use App\Models\Vault;
use App\Support\Domain;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
 *    request's real browser Origin, which page JS cannot forge. It also
 *    honours an item staged by the vault "Autofill" button when that item's
 *    domain matches the Origin. Rotate it independently of the device token.
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

        $includePasswords = (bool) config('vault.api_returns_passwords');
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

            $needle = Domain::registrable($host);

            // The vault "Autofill" button stages the exact item the user picked.
            // Honour it only when the page's domain matches the staged item's,
            // then consume it (single use); otherwise fall through to search.
            if ($staged = $this->takeStagedItem($user, $needle)) {
                return $this->matches([$staged], $includePasswords);
            }
        } else {
            $query = trim((string) $request->query('url', $request->query('q', '')));

            if ($query === '') {
                return response()->json(['message' => 'Pass ?url= or ?q= to search.'], 422);
            }

            // A full URL is matched by host (www. stripped); anything else is a
            // plain name/url substring search.
            $host = parse_url(Str::startsWith($query, ['http://', 'https://']) ? $query : "https://{$query}", PHP_URL_HOST);
            $needle = $host ? preg_replace('/^www\./', '', strtolower($host)) : strtolower($query);
        }

        $items = Item::query()
            ->whereIn('vault_id', Vault::forUser($user)->pluck('id'))
            ->where(function ($q) use ($needle) {
                $q->where('name', 'like', "%{$needle}%")
                    ->orWhere('url', 'like', "%{$needle}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        return $this->matches($items, $includePasswords);
    }

    /**
     * Pull and consume the item staged for this user, but only if its domain
     * matches the requesting page. Returns null when nothing valid is staged.
     */
    private function takeStagedItem(User $user, string $domain): ?Item
    {
        $staged = Cache::get("fill_stage:{$user->id}");

        if (! is_array($staged) || ($staged['domain'] ?? null) !== $domain) {
            return null;
        }

        Cache::forget("fill_stage:{$user->id}");

        return Item::query()
            ->whereIn('vault_id', Vault::forUser($user)->pluck('id'))
            ->find($staged['item_id']);
    }

    /**
     * @param  iterable<Item>  $items
     */
    private function matches(iterable $items, bool $includePasswords): JsonResponse
    {
        $data = collect($items)->map(fn (Item $item) => array_filter([
            'id' => $item->id,
            'name' => $item->name,
            'url' => $item->url,
            'username' => $item->username,
            'password' => $includePasswords ? $item->password : null,
            'totp' => $item->totp_secret !== null ? Totp::code($item->totp_secret) : null,
        ], fn ($value) => $value !== null))->values();

        return response()
            ->json(['matches' => $data])
            ->header('Cache-Control', 'no-store, private');
    }
}
