<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Vault;
use App\Support\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Stages the exact item the user tapped "Autofill" on in the vault list, so the
 * in-page filler fills that credential instead of guessing by Origin (which
 * fixes multi-account sites). Called by the PWA with the session cookie.
 *
 * Only an item id + domain is stored, briefly and single-use; the credential
 * itself still only leaves through the Origin-scoped lookup (see
 * LookupController), so staging never widens what the filler can read.
 */
class FillStageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        $item = Item::query()
            ->whereIn('vault_id', Vault::forUser($user)->pluck('id'))
            ->find($data['item_id']);

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $domain = Domain::fromUrl($item->url);

        if ($domain === null) {
            return response()->json(['message' => 'This item has no website to autofill.'], 422);
        }

        Cache::put(
            "fill_stage:{$user->id}",
            ['item_id' => $item->id, 'domain' => $domain],
            now()->addSeconds((int) config('vault.fill_stage_seconds', 60)),
        );

        return response()->json(['domain' => $domain]);
    }
}
