<?php

namespace App\Weather\Dto;

final readonly class Outlook
{
    public function __construct(
        public string $label,
        public string $text,
    ) {
    }
}
