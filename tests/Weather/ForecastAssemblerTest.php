<?php

namespace App\Tests\Weather;

use App\Tests\Support\ForecastFixture;
use App\Weather\ForecastAssembler;
use App\Weather\Place;
use PHPUnit\Framework\TestCase;

final class ForecastAssemblerTest extends TestCase
{
    public function testAssembleBuildsSaintPetersburgPage(): void
    {
        $page = (new ForecastAssembler())->assemble(ForecastFixture::payload(), Place::saintPetersburg());

        self::assertSame('Санкт-Петербург', $page->city);
        self::assertSame('23:30', $page->localTime);
        self::assertSame('+6°', $page->current->temperature);
        self::assertSame('+4°', $page->current->apparentTemperature);
        self::assertSame('+8°', $page->current->yesterdayTemperature);
        self::assertSame('Пасмурно', $page->current->condition);
        self::assertSame('Небольшой дождь будет ночью, около 2:00', $page->current->summary);
        self::assertSame('1,4 м/с, СВ', $page->facts[0]->value);
        self::assertSame('752', $page->facts[1]->value);
        self::assertSame('в норме', $page->facts[1]->caption);
        self::assertSame('84%', $page->facts[2]->value);
        self::assertSame('Высокая', $page->facts[2]->caption);

        self::assertSame('Сегодня, 10 октября', $page->days[0]->title);
        self::assertSame('10 ч 42 мин', $page->days[0]->daylight);
        self::assertSame('+8°', $page->days[0]->parts[0]->temperature);
        self::assertSame('6,3 м/с, ЮЗ', $page->days[0]->parts[0]->wind);
        self::assertSame('Завтра, 11 октября', $page->days[1]->title);
        self::assertSame('временами осадки · +5°…+12° · ветер до 4 м/с', $page->outlook[2]->text);

        $labels = array_map(static fn ($entry) => $entry->label, $page->timeline);
        self::assertContains('вс, 00:00', $labels);
        self::assertContains('07:27', $labels);
        self::assertNotEmpty($page->faq);
        self::assertStringContainsString('утром +8°', $page->faq[1]->answer);
    }
}
