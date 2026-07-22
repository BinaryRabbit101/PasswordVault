<?php

namespace App\Services\Import;

use App\Services\Import\DTO\ParsedLastPassRow;
use Illuminate\Support\Collection;

readonly class ParseResult
{
    /**
     * @param  Collection<int, ParsedLastPassRow>  $rows
     * @param  array<int, array{row: int, reason: string}>  $errors
     */
    public function __construct(
        public Collection $rows,
        public array $errors,
    ) {}
}
