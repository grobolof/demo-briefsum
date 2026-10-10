<?php

namespace App\Weather;

final class WeatherFormat
{
    public static function temperature(float $celsius): string
    {
        $rounded = (int) round($celsius);
        if ($rounded > 0) {
            return '+'.$rounded.'°';
        }
        if ($rounded < 0) {
            return '−'.abs($rounded).'°';
        }

        return '0°';
    }

    public static function windSpeed(float $metersPerSecond): string
    {
        $formatted = number_format($metersPerSecond, 1, ',', '');
        if (str_ends_with($formatted, ',0')) {
            return substr($formatted, 0, -2);
        }

        return $formatted;
    }

    public static function windShort(float $degrees): string
    {
        return self::DIRECTIONS_SHORT[self::directionIndex($degrees)];
    }

    public static function windFull(float $degrees): string
    {
        return self::DIRECTIONS_FULL[self::directionIndex($degrees)];
    }

    public static function windStrength(float $metersPerSecond): string
    {
        return match (true) {
            $metersPerSecond < 0.5 => 'Штиль',
            $metersPerSecond < 5 => 'Слабый',
            $metersPerSecond < 10 => 'Умеренный',
            $metersPerSecond < 15 => 'Сильный',
            default => 'Очень сильный',
        };
    }

    public static function pressureMm(float $hectopascals): int
    {
        return (int) round($hectopascals * 0.750061683);
    }

    public static function pressureLabel(int $mmHg): string
    {
        return match (true) {
            $mmHg < 740 => 'пониженное',
            $mmHg > 770 => 'повышенное',
            default => 'в норме',
        };
    }

    public static function humidityLabel(int $percent): string
    {
        return match (true) {
            $percent < 30 => 'Низкая',
            $percent < 60 => 'Комфортная',
            $percent < 80 => 'Повышенная',
            default => 'Высокая',
        };
    }

    public static function uvLabel(float $uvIndex): string
    {
        return match (true) {
            $uvIndex < 3 => 'низкий',
            $uvIndex < 6 => 'умеренный',
            $uvIndex < 8 => 'высокий',
            $uvIndex < 11 => 'очень высокий',
            default => 'экстремальный',
        };
    }

    public static function capitalize(string $text): string
    {
        if ('' === $text) {
            return '';
        }

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    public static function weekday(int $phpWeekday): string
    {
        return self::WEEKDAYS[$phpWeekday] ?? '';
    }

    public static function weekdayShort(int $phpWeekday): string
    {
        return self::WEEKDAYS_SHORT[$phpWeekday] ?? '';
    }

    public static function month(int $month): string
    {
        return self::MONTHS[$month] ?? '';
    }

    public static function daylight(\DateTimeImmutable $sunrise, \DateTimeImmutable $sunset): string
    {
        $minutes = (int) round(($sunset->getTimestamp() - $sunrise->getTimestamp()) / 60);
        if ($minutes < 0) {
            $minutes = 0;
        }

        return intdiv($minutes, 60).' ч '.($minutes % 60).' мин';
    }

    private static function directionIndex(float $degrees): int
    {
        $normalized = fmod($degrees, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }

        return ((int) round($normalized / 45.0)) % 8;
    }

    private const DIRECTIONS_SHORT = ['С', 'СВ', 'В', 'ЮВ', 'Ю', 'ЮЗ', 'З', 'СЗ'];

    private const DIRECTIONS_FULL = [
        'северный',
        'северо-восточный',
        'восточный',
        'юго-восточный',
        'южный',
        'юго-западный',
        'западный',
        'северо-западный',
    ];

    private const WEEKDAYS = [
        'Воскресенье',
        'Понедельник',
        'Вторник',
        'Среда',
        'Четверг',
        'Пятница',
        'Суббота',
    ];

    private const WEEKDAYS_SHORT = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];

    private const MONTHS = [
        1 => 'января',
        2 => 'февраля',
        3 => 'марта',
        4 => 'апреля',
        5 => 'мая',
        6 => 'июня',
        7 => 'июля',
        8 => 'августа',
        9 => 'сентября',
        10 => 'октября',
        11 => 'ноября',
        12 => 'декабря',
    ];
}
