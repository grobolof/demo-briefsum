<?php

namespace App\Tests\Weather;

use App\Tests\Support\ForecastFixture;
use App\Weather\OpenMeteoClient;
use App\Weather\Place;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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
}
