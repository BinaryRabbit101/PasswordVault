<?php

namespace App\Services\Import;

use App\Services\Import\DTO\ParsedLastPassRow;
use Illuminate\Support\Collection;
use League\Csv\Reader;
use Throwable;

/**
 * Parses a LastPass CSV export (columns: url,username,password,totp,extra,name,grouping,fav).
 *
 * Tolerates a UTF-8 BOM, CRLF line endings, missing trailing columns, and
 * quoted multiline notes. LastPass marks secure notes with the sentinel URL
 * "http://sn", which is mapped to a null URL.
 */
class LastPassCsvParser
{
    private const SECURE_NOTE_URL = 'http://sn';

    private const EXPECTED_HEADER = ['url', 'username', 'password', 'totp', 'extra', 'name', 'grouping', 'fav'];

    public function parse(string $csv): ParseResult
    {
        $csv = ltrim($csv, "\u{FEFF}");

        /** @var Collection<int, ParsedLastPassRow> $rows */
        $rows = collect();
        $errors = [];

        if (trim($csv) === '') {
            return new ParseResult($rows, [['row' => 0, 'reason' => 'The CSV is empty.']]);
        }

        try {
            $reader = Reader::createFromString($csv);
            $reader->setHeaderOffset(0);
            $header = array_map(
                fn (string $column) => strtolower(trim($column)),
                $reader->getHeader(),
            );
        } catch (Throwable $e) {
            return new ParseResult($rows, [['row' => 0, 'reason' => 'Unreadable CSV: '.$e->getMessage()]]);
        }

        if (! in_array('name', $header, true) || ! in_array('url', $header, true)) {
            return new ParseResult($rows, [[
                'row' => 0,
                'reason' => 'This does not look like a LastPass export (expected header: '.implode(',', self::EXPECTED_HEADER).').',
            ]]);
        }

        foreach ($reader->getRecords($header) as $offset => $record) {
            $line = (int) $offset + 1;

            $name = trim((string) ($record['name'] ?? ''));
            $url = trim((string) ($record['url'] ?? ''));

            if ($name === '' && $url === '') {
                $hasData = collect($record)
                    ->except(['name', 'url'])
                    ->contains(fn ($value) => trim((string) $value) !== '');

                if ($hasData) {
                    $errors[] = ['row' => $line, 'reason' => 'Row has no name or URL.'];
                }

                continue;
            }

            if ($name === '') {
                // Fall back to the URL host so the row still imports.
                $name = (string) (parse_url($url, PHP_URL_HOST) ?: $url);
            }

            $rows->push(new ParsedLastPassRow(
                url: $url === '' || strtolower($url) === self::SECURE_NOTE_URL ? null : $url,
                username: self::nullable($record['username'] ?? null),
                password: self::nullable($record['password'] ?? null),
                totp: self::nullable($record['totp'] ?? null),
                notes: self::nullable($record['extra'] ?? null),
                name: $name,
                grouping: self::nullable($record['grouping'] ?? null),
                favorite: in_array(trim((string) ($record['fav'] ?? '')), ['1', 'true'], true),
            ));
        }

        return new ParseResult($rows, $errors);
    }

    private static function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim($value) === '' ? null : $value;
    }
}
