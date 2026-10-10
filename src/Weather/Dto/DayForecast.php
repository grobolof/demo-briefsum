<?php

namespace App\Weather\Dto;

final readonly class DayForecast
{
    /**
     * @param list<DayPart> $parts
     */
    public function __construct(
        public string $date,
        public string $weekdayShort,
        public string $stripLabel,
        public string $title,
        public string $condition,
        public string $icon,
        public string $max,
        public string $min,
        public string $windMax,
        public ?string $gustMax,
        public ?string $sunrise,
        public ?string $sunset,
        public ?string $daylight,
        public ?string $uv,
        public string $moon,
        public bool $precipitation,
        public bool $snow,
        public float $tempMax,
        public float $tempMin,
        public float $windMaxMs,
        public array $parts,
    ) {
    }
}
