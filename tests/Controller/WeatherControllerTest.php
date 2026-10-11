<?php

namespace App\Tests\Controller;

use App\Tests\Support\ForecastFixture;
use App\Weather\OpenMeteoClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class WeatherControllerTest extends WebTestCase
{
    public function testHomepageRendersForecast(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], (string) json_encode(ForecastFixture::payload())));

        $client = $this->browserWithForecast($http);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Погода в Санкт-Петербурге');
        self::assertSelectorTextContains('.temp', '+6°');
        self::assertSelectorTextContains('.condition', 'Пасмурно');
        self::assertSelectorTextContains('.summary', 'около 2:00');
        self::assertSelectorTextContains('#hourly', 'Почасовой прогноз');
        self::assertSelectorTextContains('#days', 'Прогноз на 10 дней');
        self::assertSelectorNotExists('#faq');
        self::assertSelectorTextContains('.brand', 'СиноптикЪ');
        self::assertSelectorExists('.top-bar > .brand');
        self::assertSelectorExists('.top-cluster .site-nav');
        self::assertSelectorExists('.top-menu > .nav');
        self::assertSelectorExists('.top-menu .city-picker');
        self::assertSelectorExists('.top-cluster .theme-switch');
        self::assertSelectorExists('button.nav-toggle');
        self::assertSelectorExists('a[href="https://github.com/grobolof"]');
        self::assertSelectorExists('a[href="https://github.com/grobolof/demo-weatherman"]');
        self::assertSelectorNotExists('.top-inner');
        self::assertSelectorTextContains('.site-nav', 'Прогноз');
        self::assertSelectorTextContains('.site-nav', 'Новости погоды');
        self::assertSame('/news', $client->getCrawler()->filter('.site-nav a')->eq(1)->attr('href'));
        self::assertSelectorExists('img.brand-logo');
        self::assertSelectorExists('button.theme-switch[role="switch"]');
        self::assertSame('light', $client->getCrawler()->filter('html')->attr('data-theme'));
        self::assertSame('sankt-peterburg', $client->getCrawler()->filter('select[name="city"] option[selected]')->attr('value'));
        self::assertSelectorTextContains('.foot', '59.94');
    }

    public function testHomepageSwitchesToMoscow(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], (string) json_encode(ForecastFixture::payload())));

        $client = $this->browserWithForecast($http);

        $client->request('GET', '/?city=moskva');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Погода в Москве');
        self::assertSame('moskva', $client->getCrawler()->filter('select[name="city"] option[selected]')->attr('value'));
        self::assertSelectorTextContains('.foot', '55.76');
        self::assertSelectorTextContains('.foot', '37.62');
    }

    public function testUnknownCityFallsBackToSaintPetersburg(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], (string) json_encode(ForecastFixture::payload())));

        $client = $this->browserWithForecast($http);

        $client->request('GET', '/?city=paris');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Погода в Санкт-Петербурге');
    }

    public function testHomepageReturnsServiceUnavailableWhenForecastFails(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willThrowException(new ConnectException('offline', new Request('GET', 'https://api.open-meteo.com/v1/forecast')));

        $client = $this->browserWithForecast($http);

        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('h1', 'Прогноз временно недоступен');
    }

    private function browserWithForecast(ClientInterface $http): KernelBrowser
    {
        $browser = static::createClient();
        static::getContainer()->set(OpenMeteoClient::class, new OpenMeteoClient($http, new ArrayAdapter()));

        return $browser;
    }
}
