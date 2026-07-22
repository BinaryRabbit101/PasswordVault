<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class VaultController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $vault = Vault::create([
            'name' => $data['name'],
            'type' => Vault::TYPE_SHARED,
            'created_by' => $request->user()->id,
        ]);

        // Household model: every user is a member of every shared vault.
        User::all()->each(
            fn (User $user) => $user->vaults()->syncWithoutDetaching($vault),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vault created.')]);

        return to_route('vault.index');
    }

    public function update(Request $request, Vault $vault): RedirectResponse
    {
        Gate::authorize('update', $vault);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $vault->update(['name' => $data['name']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vault renamed.')]);

        return to_route('vault.index');
    }
}
