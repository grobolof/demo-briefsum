<?php

namespace App\Weather;

final class MoonPhase
{
    public static function name(\DateTimeImmutable $moment): string
    {
        $knownNewMoon = new \DateTimeImmutable('2000-01-06 18:14:00', new \DateTimeZone('UTC'));
        $days = ($moment->setTimezone(new \DateTimeZone('UTC'))->getTimestamp() - $knownNewMoon->getTimestamp()) / 86400;
        $age = fmod($days, 29.530588853);
        if ($age < 0) {
            $age += 29.530588853;
        }

        return match (true) {
            $age < 1.0 => 'новолуние',
            $age < 6.4 => 'молодая',
            $age < 8.4 => 'первая четверть',
            $age < 13.8 => 'растущая',
            $age < 16.8 => 'полнолуние',
            $age < 21.4 => 'убывающая',
            $age < 23.4 => 'последняя четверть',
            $age < 28.5 => 'старая',
            default => 'новолуние',
        };
    }
}
