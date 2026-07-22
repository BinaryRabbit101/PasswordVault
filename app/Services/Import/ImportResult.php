<?php

namespace App\Services\Import;

readonly class ImportResult
{
    /**
     * @param  array<int, array{row: int, reason: string}>  $errors
     */
    public function __construct(
        public int $imported,
        public int $duplicates,
        public array $errors,
    ) {}
}
