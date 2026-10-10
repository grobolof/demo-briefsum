<?php

namespace App\Weather\Dto;

final readonly class DayPart
{
    public function __construct(
        public string $name,
        public string $temperature,
        public string $apparent,
        public string $condition,
        public string $icon,
        public string $wind,
        public string $humidity,
        public string $pressure,
    ) {
    }
}
