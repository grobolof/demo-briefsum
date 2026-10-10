<?php

namespace App\Weather\Dto;

final readonly class TimelineEntry
{
    public function __construct(
        public string $kind,
        public string $label,
        public ?string $temperature,
        public ?string $probability,
        public string $icon,
        public string $caption,
    ) {
    }
}
