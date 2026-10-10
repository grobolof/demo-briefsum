<?php

namespace App\Weather;

final readonly class Place
{
    public function __construct(
        public string $name,
        public string $locative,
        public string $country,
        public float $latitude,
        public float $longitude,
        public string $timezone,
    ) {
    }

    public static function saintPetersburg(): self
    {
        return new self('Санкт-Петербург', 'Санкт-Петербурге', 'Россия', 59.9386, 30.3141, 'Europe/Moscow');
    }
}
