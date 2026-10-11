<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ErrorPageTest extends WebTestCase
{
    public function testNotFoundPageMatchesTheSite(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/net-takoy-stranicy');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Страница не найдена');
        self::assertSelectorTextContains('.brand', 'СиноптикЪ');
        self::assertSelectorExists('link[href*="styles/weather.css"]');
        self::assertSelectorExists('a[href="https://github.com/grobolof"]');
        self::assertSelectorExists('a[href="https://github.com/grobolof/demo-weatherman"]');
        self::assertSelectorExists('a[href="/news"]');
    }
}
