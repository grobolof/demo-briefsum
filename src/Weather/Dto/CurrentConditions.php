<?php

namespace App\Weather\Dto;

final readonly class CurrentConditions
{
    public function __construct(
        public string $temperature,
        public string $apparentTemperature,
        public string $condition,
        public ?string $yesterdayTemperature,
        public string $summary,
        public string $icon,
    ) {
    }
}
