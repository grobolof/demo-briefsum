<?php

namespace App\Weather;

final readonly class Place
{
    public function __construct(
        public string $slug,
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
        return new self('sankt-peterburg', 'Санкт-Петербург', 'Санкт-Петербурге', 'Россия', 59.9386, 30.3141, 'Europe/Moscow');
    }

    public static function moscow(): self
    {
        return new self('moskva', 'Москва', 'Москве', 'Россия', 55.7558, 37.6173, 'Europe/Moscow');
    }

    public static function fromSlug(string $slug): self
    {
        return 'moskva' === $slug ? self::moscow() : self::saintPetersburg();
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::saintPetersburg(), self::moscow()];
    }
}
