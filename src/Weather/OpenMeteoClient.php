<?php

namespace App\Weather;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireInline;

final class OpenMeteoClient
{
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';
    private const FRESH_FOR_SECONDS = 600;
    private const KEEP_FOR_SECONDS = 30 * 24 * 60 * 60;

    public function __construct(
        #[AutowireInline(Client::class, [[
            'timeout' => 8,
            'connect_timeout' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'demo-briefsum/weather',
            ],
        ]])]
        private readonly ClientInterface $http,
        #[Autowire(service: 'cache.weather')]
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ClockInterface $clock = new Clock(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forecast(Place $place): array
    {
        $query = $this->query($place);
        $cacheKey = 'weather.open_meteo.'.hash('sha256', (string) json_encode($query, JSON_THROW_ON_ERROR));
        $cached = $this->read($cacheKey);

        if (null !== $cached && $this->isFresh($cached['fetched_at'])) {
            return $cached['data'];
        }

        try {
            $data = $this->fetch($query);
        } catch (GuzzleException|\JsonException|\RuntimeException $exception) {
            if (null !== $cached) {
                $this->logger->warning('Open-Meteo не ответил, показан сохранённый прогноз.', ['exception' => $exception]);

                return $cached['data'];
            }

            throw $exception;
        }

        $this->write($cacheKey, $data);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function query(Place $place): array
    {
        return [
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
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function fetch(array $query): array
    {
        $response = $this->http->request('GET', self::FORECAST_URL, [
            'query' => $query,
            'http_errors' => true,
        ]);

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!\is_array($data) || !isset($data['current'], $data['hourly'], $data['daily']) || !\is_array($data['current'])) {
            throw new \RuntimeException('Open-Meteo вернул неожиданный ответ.');
        }

        return $data;
    }

    /**
     * @return array{fetched_at: int, data: array<string, mixed>}|null
     */
    private function read(string $cacheKey): ?array
    {
        try {
            $item = $this->cache->getItem($cacheKey);
        } catch (\Throwable $exception) {
            $this->logger->warning('Не удалось прочитать кэш прогноза.', ['exception' => $exception]);

            return null;
        }

        if (!$item->isHit()) {
            return null;
        }

        $cached = $item->get();
        if (!\is_array($cached) || !isset($cached['fetched_at'], $cached['data']) || !\is_array($cached['data'])) {
            return null;
        }

        return [
            'fetched_at' => (int) $cached['fetched_at'],
            'data' => $cached['data'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(string $cacheKey, array $data): void
    {
        try {
            $item = $this->cache->getItem($cacheKey);
            $item->set([
                'fetched_at' => $this->clock->now()->getTimestamp(),
                'data' => $data,
            ]);
            $item->expiresAfter(self::KEEP_FOR_SECONDS);
            $this->cache->save($item);
        } catch (\Throwable $exception) {
            $this->logger->warning('Не удалось записать кэш прогноза.', ['exception' => $exception]);
        }
    }

    private function isFresh(int $fetchedAt): bool
    {
        return $this->clock->now()->getTimestamp() - $fetchedAt <= self::FRESH_FOR_SECONDS;
    }
}
