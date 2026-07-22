<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Vault;
use Illuminate\Http\Request;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    /**
     * Stream a LastPass-format CSV of every item in the user's vaults.
     * Custom fields are appended to the notes column so nothing is lost.
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
                $notes = (string) ($item->notes ?? '');

                foreach ($item->fields as $field) {
                    $notes .= ($notes === '' ? '' : "\n")."{$field->label}: {$field->value}";
                }

                $writer->insertOne([
                    $item->url ?? 'http://sn',
                    $item->username ?? '',
                    $item->password ?? '',
                    $item->totp_secret ?? '',
                    $notes,
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
}
