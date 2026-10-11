<?php

namespace App\Tests\Weather;

use App\Tests\Support\ForecastFixture;
use App\Weather\OpenMeteoClient;
use App\Weather\Place;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

final class OpenMeteoClientTest extends TestCase
{
    public function testForecastRequestsMetersPerSecondAndCachesThePayload(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.open-meteo.com/v1/forecast',
                $this->callback(function (array $options): bool {
                    $place = Place::saintPetersburg();

                    return ($options['query']['latitude'] ?? null) === $place->latitude
                        && ($options['query']['longitude'] ?? null) === $place->longitude
                        && ($options['query']['timezone'] ?? null) === 'Europe/Moscow'
                        && ($options['query']['wind_speed_unit'] ?? null) === 'ms'
                        && str_contains((string) ($options['query']['current'] ?? ''), 'temperature_2m');
                }),
            )
            ->willReturn(new Response(200, [], (string) json_encode(ForecastFixture::payload())));

        $client = new OpenMeteoClient($http, new ArrayAdapter());
        $place = Place::saintPetersburg();

        $first = $client->forecast($place);
        $second = $client->forecast($place);

        self::assertSame(6.4, $first['current']['temperature_2m']);
        self::assertSame($first, $second);
    }

    public function testStaleForecastIsRefreshedFromTheApi(): void
    {
        $fresh = ForecastFixture::payload();
        $updated = $fresh;
        $updated['current']['temperature_2m'] = 11.2;
        $calls = 0;

        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function () use (&$calls, $fresh, $updated): Response {
                ++$calls;

                return new Response(200, [], (string) json_encode(1 === $calls ? $fresh : $updated));
            });

        $clock = new MockClock('2026-10-11 12:00:00+03:00');
        $client = new OpenMeteoClient($http, new ArrayAdapter(), new NullLogger(), $clock);
        $place = Place::saintPetersburg();

        self::assertSame(6.4, $client->forecast($place)['current']['temperature_2m']);

        $clock->sleep(599);
        self::assertSame(6.4, $client->forecast($place)['current']['temperature_2m']);

        $clock->sleep(2);
        self::assertSame(11.2, $client->forecast($place)['current']['temperature_2m']);
    }

    public function testStaleForecastFallsBackToStoredDataWhenTheApiFails(): void
    {
        $calls = 0;
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function () use (&$calls): Response {
                ++$calls;
                if (1 === $calls) {
                    return new Response(200, [], (string) json_encode(ForecastFixture::payload()));
                }

                throw new ConnectException('offline', new Request('GET', 'https://api.open-meteo.com/v1/forecast'));
            });

        $clock = new MockClock('2026-10-11 12:00:00+03:00');
        $client = new OpenMeteoClient($http, new ArrayAdapter(), new NullLogger(), $clock);
        $place = Place::saintPetersburg();

        $first = $client->forecast($place);
        $clock->sleep(601);

        self::assertSame($first, $client->forecast($place));
    }
}
