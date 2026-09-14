<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Phone: the two keys the iPhone carries for /api/lookup — the
 * device key (the Vault Lookup Shortcut's header) and the fill key (embedded
 * in the in-page filler, Origin-scoped). Each can be generated, rolled and
 * revoked on its own. Replaces minting over SSH with `php artisan vault:token`,
 * which still works for the same columns.
 */
class PhoneController extends Controller
{
    private const LABELS = [
        'device_token' => 'Device key',
        'fill_token' => 'Fill key',
    ];

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Phone', [
            'phone' => [
                'lookup_endpoint' => route('api.lookup'),
                'keys' => [
                    [
                        'kind' => 'device_token',
                        'label' => self::LABELS['device_token'],
                        'token' => $user->device_token,
                        'header' => 'X-Device-Token',
                    ],
                    [
                        'kind' => 'fill_token',
                        'label' => self::LABELS['fill_token'],
                        'token' => $user->fill_token,
                        'header' => null,
                    ],
                ],
            ],
        ]);
    }

    /** Mint (or roll) one key. Rolling revokes the old value immediately. */
    public function regenerate(Request $request): RedirectResponse
    {
        $kind = $this->kind($request);
        $existed = $request->user()->{$kind} !== null;

        $request->user()->regeneratePhoneToken($kind);

        Inertia::flash('toast', ['type' => 'success', 'message' => $existed
            ? __(':label rolled. Paste the new one in — the old one no longer works.', ['label' => self::LABELS[$kind]])
            : __(':label generated.', ['label' => self::LABELS[$kind]]),
        ]);

        return to_route('phone.edit');
    }

    public function revoke(Request $request): RedirectResponse
    {
        $kind = $this->kind($request);

        $request->user()->revokePhoneToken($kind);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':label revoked.', ['label' => self::LABELS[$kind]])]);

        return to_route('phone.edit');
    }

    private function kind(Request $request): string
    {
        return $request->validate([
            'kind' => ['required', Rule::in(User::PHONE_TOKENS)],
        ])['kind'];
    }
}
