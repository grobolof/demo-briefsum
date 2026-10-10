<?php

namespace App\Weather\Dto;

final readonly class Fact
{
    public function __construct(
        public string $icon,
        public string $value,
        public string $caption,
        public string $hint,
    ) {
    }
}
