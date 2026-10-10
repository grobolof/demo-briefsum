<?php

namespace App\Weather\Dto;

final readonly class FaqItem
{
    public function __construct(
        public string $question,
        public string $answer,
    ) {
    }
}
