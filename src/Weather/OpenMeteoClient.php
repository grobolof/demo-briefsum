<?php

namespace App\Weather;

use GuzzleHttp\ClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class OpenMeteoClient
{
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    public function __construct(
        private readonly ClientInterface $http,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forecast(Place $place): array
    {
        $query = [
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'timezone' => $place->timezone,
            'forecast_days' => 11,
            'past_days' => 1,
            'wind_speed_unit' => 'ms',
            'current' => implode(',', [
                'temperature_2m',
                'relative_humidity_2m',
                'apparent_temperature',
                'is_day',
                'precipitation',
                'weather_code',
                'cloud_cover',
                'pressure_msl',
                'wind_speed_10m',
                'wind_direction_10m',
                'wind_gusts_10m',
            ]),
            'hourly' => implode(',', [
                'temperature_2m',
                'relative_humidity_2m',
                'apparent_temperature',
                'precipitation_probability',
                'precipitation',
                'weather_code',
                'pressure_msl',
                'cloud_cover',
                'wind_speed_10m',
                'wind_direction_10m',
                'wind_gusts_10m',
                'uv_index',
                'is_day',
            ]),
            'daily' => implode(',', [
                'weather_code',
                'temperature_2m_max',
                'temperature_2m_min',
                'sunrise',
                'sunset',
                'uv_index_max',
                'precipitation_sum',
                'wind_speed_10m_max',
                'wind_gusts_10m_max',
                'wind_direction_10m_dominant',
            ]),
        ];

        $cacheKey = 'weather.open_meteo.'.hash('sha256', (string) json_encode($query));

        /** @var array<string, mixed> $payload */
        $payload = $this->cache->get($cacheKey, function (ItemInterface $item) use ($query): array {
            $item->expiresAfter(600);

            $response = $this->http->request('GET', self::FORECAST_URL, [
                'query' => $query,
                'http_errors' => true,
            ]);

            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (!\is_array($data) || !isset($data['current'], $data['hourly'], $data['daily']) || !\is_array($data['current'])) {
                throw new \RuntimeException('Open-Meteo вернул неожиданный ответ.');
            }

            return $data;
        });

        return $payload;
    }
}
