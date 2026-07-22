<?php

namespace App\Services\Import\DTO;

readonly class ParsedLastPassRow
{
    public function __construct(
        public ?string $url,
        public ?string $username,
        public ?string $password,
        public ?string $totp,
        public ?string $notes,
        public string $name,
        public ?string $grouping,
        public bool $favorite,
    ) {}
}
