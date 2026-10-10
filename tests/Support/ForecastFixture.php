<?php

namespace App\Tests\Support;

final class ForecastFixture
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        $start = new \DateTimeImmutable('2026-10-09T00:00:00');
        $time = [];
        $temperature = [];
        $apparent = [];
        $humidity = [];
        $probability = [];
        $precipitation = [];
        $code = [];
        $pressure = [];
        $wind = [];
        $direction = [];
        $gust = [];
        $uv = [];
        $isDay = [];

        for ($i = 0; $i < 96; ++$i) {
            $moment = $start->modify(sprintf('+%d hours', $i));
            $hour = (int) $moment->format('G');
            $time[] = $moment->format('Y-m-d\TH:i');
            $temperature[] = 6.0;
            $apparent[] = 4.0;
            $humidity[] = 80;
            $probability[] = 0;
            $precipitation[] = 0.0;
            $code[] = 3;
            $pressure[] = 1002.6;
            $wind[] = 2.0;
            $direction[] = 45;
            $gust[] = 4.0;
            $uv[] = $hour >= 8 && $hour <= 16 ? 1.0 : 0.0;
            $isDay[] = $hour >= 7 && $hour < 18 ? 1 : 0;
        }

        $at = static function (string $stamp) use ($time): int {
            $index = array_search($stamp, $time, true);
            if (false === $index) {
                throw new \RuntimeException('В фикстуре нет часа '.$stamp);
            }

            return $index;
        };

        $yesterday = $at('2026-10-09T23:00');
        $temperature[$yesterday] = 8.2;

        $morning = $at('2026-10-10T09:00');
        $temperature[$morning] = 8.4;
        $apparent[$morning] = 3.1;
        $humidity[$morning] = 91;
        $code[$morning] = 61;
        $pressure[$morning] = 988.0;
        $wind[$morning] = 6.3;
        $direction[$morning] = 225;

        $rain = $at('2026-10-11T02:00');
        $temperature[$rain] = 6.1;
        $code[$rain] = 61;
        $probability[$rain] = 50;
        $precipitation[$rain] = 0.4;

        return [
            'current' => [
                'time' => '2026-10-10T23:30',
                'temperature_2m' => 6.4,
                'relative_humidity_2m' => 84,
                'apparent_temperature' => 4.2,
                'is_day' => 0,
                'precipitation' => 0.0,
                'weather_code' => 3,
                'cloud_cover' => 98,
                'pressure_msl' => 1002.6,
                'wind_speed_10m' => 1.4,
                'wind_direction_10m' => 45,
                'wind_gusts_10m' => 4.0,
            ],
            'hourly' => [
                'time' => $time,
                'temperature_2m' => $temperature,
                'relative_humidity_2m' => $humidity,
                'apparent_temperature' => $apparent,
                'precipitation_probability' => $probability,
                'precipitation' => $precipitation,
                'weather_code' => $code,
                'pressure_msl' => $pressure,
                'wind_speed_10m' => $wind,
                'wind_direction_10m' => $direction,
                'wind_gusts_10m' => $gust,
                'uv_index' => $uv,
                'is_day' => $isDay,
            ],
            'daily' => [
                'time' => ['2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12'],
                'weather_code' => [3, 3, 61, 2],
                'temperature_2m_max' => [9.0, 10.2, 12.4, 10.0],
                'temperature_2m_min' => [5.0, 6.2, 6.6, 5.0],
                'sunrise' => ['2026-10-09T07:20', '2026-10-10T07:24', '2026-10-11T07:27', '2026-10-12T07:29'],
                'sunset' => ['2026-10-09T18:10', '2026-10-10T18:06', '2026-10-11T18:03', '2026-10-12T18:00'],
                'uv_index_max' => [1, 1, 1, 1],
                'wind_speed_10m_max' => [5.0, 6.3, 3.2, 4.0],
                'wind_gusts_10m_max' => [8.0, 7.4, 8.1, 9.0],
            ],
        ];
    }
}
