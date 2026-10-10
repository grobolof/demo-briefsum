<?php

namespace App\Weather;

final class WeatherCodes
{
    public static function label(int $code): string
    {
        return self::LABELS[$code] ?? 'переменная облачность';
    }

    public static function isPrecipitation(int $code): bool
    {
        return $code >= 51;
    }

    public static function isSnow(int $code): bool
    {
        return \in_array($code, [71, 73, 75, 77, 85, 86], true);
    }

    public static function icon(int $code, bool $isDay): string
    {
        return match (true) {
            0 === $code => $isDay ? 'clear-day' : 'clear-night',
            1 === $code, 2 === $code => $isDay ? 'partly-day' : 'partly-night',
            3 === $code => 'overcast',
            45 === $code, 48 === $code => 'fog',
            $code >= 51 && $code <= 57 => 'drizzle',
            ($code >= 71 && $code <= 77) || 85 === $code || 86 === $code => 'snow',
            $code >= 95 => 'thunder',
            $code >= 61 => 'rain',
            default => 'cloudy',
        };
    }

    private const LABELS = [
        0 => 'ясно',
        1 => 'малооблачно',
        2 => 'облачно с прояснениями',
        3 => 'пасмурно',
        45 => 'туман',
        48 => 'туман с изморозью',
        51 => 'слабая морось',
        53 => 'морось',
        55 => 'сильная морось',
        56 => 'слабая ледяная морось',
        57 => 'ледяная морось',
        61 => 'небольшой дождь',
        63 => 'дождь',
        65 => 'сильный дождь',
        66 => 'слабый ледяной дождь',
        67 => 'ледяной дождь',
        71 => 'небольшой снег',
        73 => 'снег',
        75 => 'сильный снег',
        77 => 'снежные зёрна',
        80 => 'небольшой ливень',
        81 => 'ливень',
        82 => 'сильный ливень',
        85 => 'небольшой снегопад',
        86 => 'сильный снегопад',
        95 => 'гроза',
        96 => 'гроза с градом',
        99 => 'сильная гроза с градом',
    ];
}
