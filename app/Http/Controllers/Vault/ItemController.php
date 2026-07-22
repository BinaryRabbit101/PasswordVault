<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\StoreItemRequest;
use App\Http\Requests\Vault\UpdateItemRequest;
use App\Models\Folder;
use App\Models\Item;
use App\Models\Vault;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $vaults = Vault::forUser($user)->orderBy('type')->orderBy('name')->get();

        $items = Item::query()
            ->whereIn('vault_id', $vaults->modelKeys())
            ->with('folder:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'vault_id' => $item->vault_id,
                'name' => $item->name,
                'url' => $item->url,
                'username' => $item->username,
                'folder' => $item->folder?->name,
                'favorite' => $item->favorite,
                'has_totp' => $item->totp_secret !== null,
                'has_notes' => $item->notes !== null,
            ]);

        return Inertia::render('vault/Index', [
            'vaults' => $vaults->map(fn (Vault $vault) => [
                'id' => $vault->id,
                'name' => $vault->name,
                'type' => $vault->type,
            ]),
            'items' => $items,
            'clipboardClearSeconds' => config('vault.clipboard_clear_seconds'),
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $vault = Vault::findOrFail((int) $data['vault_id']);

        try {
            $item = $vault->items()->create([
                ...collect($data)->except(['fields', 'folder'])->all(),
                'folder_id' => $this->resolveFolder($vault, $data['folder'] ?? null),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => __('An identical item already exists in this vault.'),
            ]);
        }

        $this->syncFields($item, $data['fields'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item added.')]);

        return to_route('vault.index');
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        Gate::authorize('update', $item);

        $data = $request->validated();
        $vault = isset($data['vault_id']) ? Vault::findOrFail((int) $data['vault_id']) : $item->vault;

        try {
            $item->update([
                ...collect($data)->except(['fields', 'folder'])->all(),
                'folder_id' => $this->resolveFolder($vault, $data['folder'] ?? null),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => __('An identical item already exists in this vault.'),
            ]);
        }

        if (array_key_exists('fields', $data)) {
            $this->syncFields($item, $data['fields']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item updated.')]);

        return to_route('vault.index');
    }

    public function destroy(Item $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $item->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item deleted.')]);

        return to_route('vault.index');
    }

    /**
     * Secrets are fetched on demand so they never ride in the page payload.
     */
    public function secrets(Item $item): JsonResponse
    {
        Gate::authorize('view', $item);

        return response()->json([
            'password' => $item->password,
            'totp_secret' => $item->totp_secret,
            'notes' => $item->notes,
            'fields' => $item->fields->map(fn ($field) => [
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
                'value' => $field->value,
                'is_secret' => $field->is_secret,
            ]),
        ])->header('Cache-Control', 'no-store, private');
    }

    protected function resolveFolder(Vault $vault, ?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return Folder::firstOrCreate([
            'vault_id' => $vault->id,
            'name' => trim($name),
        ])->id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    protected function syncFields(Item $item, array $fields): void
    {
        $item->fields()->delete();

        foreach (array_values($fields) as $index => $field) {
            $item->fields()->create([
                'label' => $field['label'],
                'type' => $field['type'],
                'value' => $field['value'] ?? null,
                'is_secret' => $field['is_secret'] ?? true,
                'sort_order' => $index,
            ]);
        }
    }
}
