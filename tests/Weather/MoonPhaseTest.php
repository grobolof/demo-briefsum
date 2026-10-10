<?php

namespace App\Tests\Weather;

use App\Weather\MoonPhase;
use PHPUnit\Framework\TestCase;

final class MoonPhaseTest extends TestCase
{
    public function testKnownNewAndFullMoon(): void
    {
        $newMoon = new \DateTimeImmutable('2000-01-06 18:14:00', new \DateTimeZone('UTC'));

        self::assertSame('новолуние', MoonPhase::name($newMoon));
        self::assertSame('полнолуние', MoonPhase::name($newMoon->modify('+15 days')));
    }
}
