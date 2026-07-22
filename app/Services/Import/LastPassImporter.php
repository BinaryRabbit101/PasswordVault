<?php

namespace App\Services\Import;

use App\Models\Folder;
use App\Models\Item;
use App\Models\Vault;
use App\Observers\ItemObserver;
use App\Services\Import\DTO\ParsedLastPassRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class LastPassImporter
{
    /**
     * @param  Collection<int, ParsedLastPassRow>  $rows
     */
    public function import(Collection $rows, Vault $vault): ImportResult
    {
        $imported = 0;
        $duplicates = 0;
        $errors = [];

        // One import would otherwise fire a push per row to the other member.
        ItemObserver::$muted = true;

        try {
            $this->run($rows, $vault, $imported, $duplicates, $errors);
        } finally {
            ItemObserver::$muted = false;
        }

        return new ImportResult($imported, $duplicates, $errors);
    }

    /**
     * @param  Collection<int, ParsedLastPassRow>  $rows
     * @param  array<int, array{row: int, reason: string}>  $errors
     */
    private function run(Collection $rows, Vault $vault, int &$imported, int &$duplicates, array &$errors): void
    {
        DB::transaction(function () use ($rows, $vault, &$imported, &$duplicates, &$errors): void {
            $existingHashes = $vault->items()
                ->withTrashed()
                ->pluck('dedup_hash')
                ->flip();

            $folderIds = [];

            foreach ($rows as $index => $row) {
                $hash = Item::dedupHashFor($row->name, $row->url, $row->username);

                if ($existingHashes->has($hash)) {
                    $duplicates++;

                    continue;
                }

                try {
                    $folderId = null;

                    if ($row->grouping !== null) {
                        $folderId = $folderIds[$row->grouping] ??= Folder::firstOrCreate([
                            'vault_id' => $vault->id,
                            'name' => $row->grouping,
                        ])->id;
                    }

                    $vault->items()->create([
                        'folder_id' => $folderId,
                        'name' => $row->name,
                        'url' => $row->url,
                        'username' => $row->username,
                        'password' => $row->password,
                        'notes' => $row->notes,
                        'totp_secret' => $row->totp,
                        'favorite' => $row->favorite,
                    ]);

                    $existingHashes->put($hash, 1);
                    $imported++;
                } catch (Throwable $e) {
                    $errors[] = ['row' => $index + 1, 'reason' => $e->getMessage()];
                }
            }
        });
    }
}
