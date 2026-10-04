<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\StoreItemRequest;
use App\Http\Requests\Vault\UpdateItemRequest;
use App\Models\Folder;
use App\Models\Item;
use App\Models\ItemField;
use App\Models\ItemFieldHistory;
use App\Models\Vault;
use App\Support\FieldRoles;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            // Labels and types only — no field value is loaded or decrypted
            // for the list, just whether it is filled.
            ->with(['folder:id,name', 'fields' => fn ($query) => $query
                ->select(['id', 'item_id', 'label', 'type', 'autofill'])
                ->selectRaw('value is not null as filled')])
            ->orderBy('name')
            ->get()
            ->map(function (Item $item) {
                $fields = $item->fields->map(fn (ItemField $field) => [
                    'label' => $field->label,
                    'type' => $field->type,
                    'autofill' => $field->autofill,
                    'value' => $field->getAttribute('filled') ? 'filled' : null,
                ]);

                return [
                    'id' => $item->id,
                    'vault_id' => $item->vault_id,
                    'name' => $item->name,
                    'url' => $item->url,
                    'username' => $item->username,
                    'folder' => $item->folder?->name,
                    'favorite' => $item->favorite,
                    // What the quick-copy buttons copy, by the field's own
                    // name; null means there is nothing to copy.
                    'username_label' => FieldRoles::usernameLabel($fields),
                    'password_label' => FieldRoles::passwordLabel($fields),
                ];
            });

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
        $fields = $data['fields'] ?? [];

        $item = new Item([
            'name' => $data['name'],
            'favorite' => $data['favorite'] ?? false,
            'folder_id' => $this->resolveFolder($vault, $data['folder'] ?? null),
        ]);
        $item->vault_id = $vault->id;
        $item->applyDerived($fields);

        $this->rejectingDuplicates(function () use ($item, $fields): void {
            $item->save();
            $item->syncFields($fields);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item added.')]);

        return to_route('vault.index');
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        Gate::authorize('update', $item);

        $data = $request->validated();
        $vault = isset($data['vault_id']) ? Vault::findOrFail((int) $data['vault_id']) : $item->vault;

        $item->fill([
            ...collect($data)->only(['vault_id', 'name', 'favorite'])->all(),
            'folder_id' => $this->resolveFolder($vault, $data['folder'] ?? null),
        ]);

        $this->rejectingDuplicates(function () use ($item, $data): void {
            $fieldsChanged = array_key_exists('fields', $data) && $item->syncFields($data['fields']);

            // A fields-only edit still counts as an update, so shared-vault
            // members hear about a changed password.
            if ($fieldsChanged) {
                $item->updated_at = now();
            }

            $item->save();
        });

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
     * `password` is whichever field autofill would use.
     */
    public function secrets(Item $item): JsonResponse
    {
        Gate::authorize('view', $item);

        return response()->json([
            'password' => $item->loginPassword(),
            'fields' => $item->fields->map(fn (ItemField $field) => [
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
                'autofill' => $field->autofill,
                'value' => $field->value,
            ]),
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * A field's previous values, fetched on demand so they never ride in the
     * page payload.
     */
    public function fieldHistory(Item $item, ItemField $field): JsonResponse
    {
        Gate::authorize('view', $item);

        abort_unless($field->item_id === $item->id, 404);

        return response()->json([
            'history' => $field->histories->map(fn (ItemFieldHistory $entry) => [
                'id' => $entry->id,
                'value' => $entry->value,
                'created_at' => $entry->created_at,
            ]),
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Run a save in a transaction, turning the dedup constraint into a form
     * error (and rolling back any field changes with it).
     *
     * @param  Closure(): void  $save
     */
    protected function rejectingDuplicates(Closure $save): void
    {
        try {
            DB::transaction($save);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => __('An identical item already exists in this vault.'),
            ]);
        }
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
}
