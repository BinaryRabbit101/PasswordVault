<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\Vault;
use App\Services\Import\LastPassCsvParser;
use App\Services\Import\LastPassImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    private const CACHE_TTL_MINUTES = 15;

    private const SESSION_KEY = 'vault_import_id';

    public function create(Request $request, LastPassCsvParser $parser): Response
    {
        $preview = null;
        $importId = $request->session()->get(self::SESSION_KEY);

        // The raw CSV only ever lives encrypted in the cache; the preview is
        // re-parsed on each visit instead of storing rows in the session.
        if (is_string($importId)) {
            $encrypted = Cache::get(self::cacheKey($importId));

            if (is_string($encrypted)) {
                $result = $parser->parse(Crypt::decryptString($encrypted));

                $preview = [
                    'importId' => $importId,
                    'total' => $result->rows->count(),
                    'errors' => $result->errors,
                    'rows' => $result->rows->take(25)->map(fn ($row) => [
                        'name' => $row->name,
                        'url' => $row->url,
                        'username' => $row->username,
                        'folder' => $row->grouping,
                        'has_password' => $row->password !== null,
                        'has_totp' => $row->totp !== null,
                        'has_notes' => $row->notes !== null,
                        'favorite' => $row->favorite,
                    ])->values(),
                ];
            } else {
                $request->session()->forget(self::SESSION_KEY);
            }
        }

        return Inertia::render('vault/Import', [
            'vaults' => Vault::forUser($request->user())
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(fn (Vault $vault) => [
                    'id' => $vault->id,
                    'name' => $vault->name,
                    'type' => $vault->type,
                ]),
            'preview' => $preview,
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'csv' => ['nullable', 'string', 'max:10485760'],
            'file' => ['nullable', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:10240'],
        ]);

        $csv = (string) $request->input('csv', '');

        if ($request->hasFile('file')) {
            $csv = (string) file_get_contents($request->file('file')->getRealPath());
        }

        if (trim($csv) === '') {
            return back()->withErrors(['csv' => __('Paste your LastPass export or choose a file.')]);
        }

        // Encrypted at rest: the cache store is database-backed.
        $importId = (string) Str::uuid();
        Cache::put(
            self::cacheKey($importId),
            Crypt::encryptString($csv),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
        );

        $request->session()->put(self::SESSION_KEY, $importId);

        return to_route('vault.import.create');
    }

    public function store(
        Request $request,
        LastPassCsvParser $parser,
        LastPassImporter $importer,
    ): RedirectResponse {
        $data = $request->validate([
            'import_id' => ['required', 'string'],
            'vault_id' => [
                'required',
                'integer',
                Rule::exists('user_vault', 'vault_id')->where('user_id', $request->user()->id),
            ],
        ]);

        $encrypted = Cache::get(self::cacheKey($data['import_id']));

        if (! is_string($encrypted)) {
            return to_route('vault.import.create')
                ->withErrors(['csv' => __('The import expired. Paste your export again.')]);
        }

        $result = $parser->parse(Crypt::decryptString($encrypted));
        $outcome = $importer->import($result->rows, Vault::findOrFail((int) $data['vault_id']));

        Cache::forget(self::cacheKey($data['import_id']));
        $request->session()->forget(self::SESSION_KEY);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':imported imported, :duplicates duplicates skipped.', [
                'imported' => $outcome->imported,
                'duplicates' => $outcome->duplicates,
            ]),
        ]);

        return to_route('vault.index');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $importId = $request->session()->pull(self::SESSION_KEY);

        if (is_string($importId)) {
            Cache::forget(self::cacheKey($importId));
        }

        return to_route('vault.import.create');
    }

    private static function cacheKey(string $importId): string
    {
        return "vault-import:{$importId}";
    }
}
