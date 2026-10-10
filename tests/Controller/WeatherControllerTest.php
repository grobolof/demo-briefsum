<?php

namespace App\Tests\Controller;

use App\Tests\Support\ForecastFixture;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WeatherControllerTest extends WebTestCase
{
    public function testHomepageRendersForecast(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], (string) json_encode(ForecastFixture::payload())));

        $client = static::createClient();
        static::getContainer()->set(ClientInterface::class, $http);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Погода в Санкт-Петербурге');
        self::assertSelectorTextContains('.temp', '+6°');
        self::assertSelectorTextContains('.condition', 'Пасмурно');
        self::assertSelectorTextContains('.summary', 'около 2:00');
        self::assertSelectorTextContains('#hourly', 'Почасовой прогноз');
        self::assertSelectorTextContains('#days', 'Прогноз на 10 дней');
        self::assertSelectorExists('#faq details');
    }

    public function testHomepageReturnsServiceUnavailableWhenForecastFails(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willThrowException(new ConnectException('offline', new Request('GET', 'https://api.open-meteo.com/v1/forecast')));

        $client = static::createClient();
        static::getContainer()->set(ClientInterface::class, $http);

        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('h1', 'Прогноз временно недоступен');
    }
}
