<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemField;
use App\Models\Vault;
use Illuminate\Http\Request;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    /**
     * Stream a LastPass-format CSV of every item in the user's vaults.
     *
     * The fields autofill uses fill LastPass's url/username/password/totp
     * columns; notes and every other field go in the extra column as
     * "Label: value" lines so nothing is lost.
     */
    public function download(Request $request): Response
    {
        $vaultIds = Vault::forUser($request->user())->pluck('id');

        $writer = Writer::createFromString();
        $writer->insertOne(['url', 'username', 'password', 'totp', 'extra', 'name', 'grouping', 'fav']);

        Item::query()
            ->whereIn('vault_id', $vaultIds)
            ->with(['folder:id,name', 'fields'])
            ->orderBy('name')
            ->each(function (Item $item) use ($writer): void {
                $columns = [
                    'url' => $item->url,
                    'username' => $item->username,
                    'password' => $item->loginPassword(),
                    'totp' => $item->totpSecret(),
                ];

                $writer->insertOne([
                    $columns['url'] ?? 'http://sn',
                    $columns['username'] ?? '',
                    $columns['password'] ?? '',
                    $columns['totp'] ?? '',
                    $this->extra($item, $columns),
                    $item->name,
                    $item->folder->name ?? '',
                    $item->favorite ? '1' : '0',
                ]);
            });

        return response($writer->toString(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="vault-export.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Every field not already written to a LastPass column, in order.
     *
     * @param  array<string, string|null>  $columns
     */
    private function extra(Item $item, array $columns): string
    {
        $lines = [];

        foreach ($item->fields as $field) {
            /** @var ItemField $field */
            if ($field->value === null) {
                continue;
            }

            $column = array_search($field->value, $columns, true);

            if ($column !== false) {
                unset($columns[$column]); // each column takes one field

                continue;
            }

            $lines[] = $field->type === 'note' && $field->label === 'Notes'
                ? $field->value
                : "{$field->label}: {$field->value}";
        }

        return implode("\n", $lines);
    }
}
