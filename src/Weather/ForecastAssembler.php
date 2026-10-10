<?php

namespace App\Weather;

use App\Weather\Dto\CurrentConditions;
use App\Weather\Dto\DayForecast;
use App\Weather\Dto\DayPart;
use App\Weather\Dto\Fact;
use App\Weather\Dto\FaqItem;
use App\Weather\Dto\Outlook;
use App\Weather\Dto\TimelineEntry;
use App\Weather\Dto\WeatherPage;

final class ForecastAssembler
{
    private const DAYS = 10;

    /**
     * @param array<string, mixed> $payload
     */
    public function assemble(array $payload, Place $place): WeatherPage
    {
        $current = $payload['current'] ?? null;
        $hourly = $payload['hourly'] ?? null;
        $daily = $payload['daily'] ?? null;
        if (!\is_array($current) || !\is_array($hourly) || !\is_array($daily) || !isset($current['time'])) {
            throw new \RuntimeException('В ответе Open-Meteo нет прогноза.');
        }

        $timezone = new \DateTimeZone($place->timezone);
        $now = new \DateTimeImmutable((string) $current['time'], $timezone);
        $hours = $this->indexHours($hourly);
        $today = $now->format('Y-m-d');
        $days = $this->buildDays($daily, $hours, $today, $timezone, $now);
        if ([] === $days) {
            throw new \RuntimeException('Open-Meteo не вернул прогноз на сегодня.');
        }

        $temperature = (float) $current['temperature_2m'];
        $apparent = (float) $current['apparent_temperature'];
        $code = (int) $current['weather_code'];
        $isDay = (bool) ($current['is_day'] ?? true);
        $wind = (float) $current['wind_speed_10m'];
        $direction = (float) $current['wind_direction_10m'];
        $humidity = (int) round((float) $current['relative_humidity_2m']);
        $pressure = WeatherFormat::pressureMm((float) $current['pressure_msl']);
        $hourStart = $now->setTime((int) $now->format('G'), 0);
        $yesterday = $hours[$hourStart->modify('-1 day')->format('Y-m-d\TH:i')] ?? null;
        $uvNow = $hours[$hourStart->format('Y-m-d\TH:i')]['uv'] ?? 0.0;
        $summary = $this->upcomingSummary($hourStart, $hours);

        $conditions = new CurrentConditions(
            temperature: WeatherFormat::temperature($temperature),
            apparentTemperature: WeatherFormat::temperature($apparent),
            condition: WeatherFormat::capitalize(WeatherCodes::label($code)),
            yesterdayTemperature: null === $yesterday ? null : WeatherFormat::temperature($yesterday['temperature']),
            summary: $summary,
            icon: WeatherCodes::icon($code, $isDay),
        );

        $facts = [
            new Fact(
                'wind',
                WeatherFormat::windSpeed($wind).' м/с, '.WeatherFormat::windShort($direction),
                WeatherFormat::windStrength($wind),
                'Ветер '.WeatherFormat::windFull($direction),
            ),
            new Fact(
                'pressure',
                (string) $pressure,
                WeatherFormat::pressureLabel($pressure),
                $pressure.' мм рт. ст.',
            ),
            new Fact(
                'humidity',
                $humidity.'%',
                WeatherFormat::humidityLabel($humidity),
                'Относительная влажность',
            ),
            new Fact(
                'uv',
                (string) (int) round($uvNow),
                WeatherFormat::uvLabel($uvNow),
                'УФ-индекс',
            ),
        ];

        return new WeatherPage(
            city: $place->name,
            cityIn: $place->locative,
            country: $place->country,
            localTime: $now->format('H:i'),
            current: $conditions,
            facts: $facts,
            outlook: $this->outlooks($summary, $conditions, $wind, $days),
            wellbeing: [
                new Fact('pressure', (string) $pressure, 'Давление', WeatherFormat::pressureLabel($pressure)),
                new Fact('uv', (string) (int) round($uvNow), 'УФ-индекс', WeatherFormat::uvLabel($uvNow)),
                new Fact('humidity', $humidity.'%', 'Влажность', WeatherFormat::humidityLabel($humidity)),
                new Fact('moon', MoonPhase::name($now), 'Луна', 'фаза луны'),
            ],
            timeline: $this->timeline($hourStart, $hours, $days),
            days: $days,
            faq: $this->faq($place, $conditions, $wind, $direction, $humidity, $pressure, $days[0], $summary),
        );
    }

    /**
     * @param array<string, mixed>        $hourly
     * @return array<string, array{temperature: float, apparent: float, humidity: int, probability: ?int, precipitation: float, code: int, pressure: float, wind: float, direction: float, gust: float, uv: float, isDay: bool}>
     */
    private function indexHours(array $hourly): array
    {
        $times = $hourly['time'] ?? null;
        if (!\is_array($times)) {
            return [];
        }

        $indexed = [];
        foreach ($times as $i => $time) {
            if (!\is_string($time)) {
                continue;
            }
            $temperature = $this->num($hourly['temperature_2m'][$i] ?? null);
            if (null === $temperature) {
                continue;
            }
            $probability = $hourly['precipitation_probability'][$i] ?? null;
            $indexed[substr($time, 0, 16)] = [
                'temperature' => $temperature,
                'apparent' => $this->num($hourly['apparent_temperature'][$i] ?? null) ?? $temperature,
                'humidity' => (int) round($this->num($hourly['relative_humidity_2m'][$i] ?? null) ?? 0),
                'probability' => \is_int($probability) || \is_float($probability) ? (int) round((float) $probability) : null,
                'precipitation' => $this->num($hourly['precipitation'][$i] ?? null) ?? 0.0,
                'code' => (int) ($hourly['weather_code'][$i] ?? 0),
                'pressure' => $this->num($hourly['pressure_msl'][$i] ?? null) ?? 0.0,
                'wind' => $this->num($hourly['wind_speed_10m'][$i] ?? null) ?? 0.0,
                'direction' => $this->num($hourly['wind_direction_10m'][$i] ?? null) ?? 0.0,
                'gust' => $this->num($hourly['wind_gusts_10m'][$i] ?? null) ?? 0.0,
                'uv' => $this->num($hourly['uv_index'][$i] ?? null) ?? 0.0,
                'isDay' => (bool) ($hourly['is_day'][$i] ?? true),
            ];
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $daily
     * @param array<string, array{temperature: float, apparent: float, humidity: int, probability: ?int, precipitation: float, code: int, pressure: float, wind: float, direction: float, gust: float, uv: float, isDay: bool}> $hours
     *
     * @return list<DayForecast>
     */
    private function buildDays(array $daily, array $hours, string $today, \DateTimeZone $timezone, \DateTimeImmutable $now): array
    {
        $dates = $daily['time'] ?? null;
        if (!\is_array($dates)) {
            return [];
        }
        $todayIndex = array_search($today, $dates, true);
        if (false === $todayIndex) {
            return [];
        }

        $tomorrow = $now->modify('+1 day')->format('Y-m-d');
        $days = [];
        for ($offset = 0; $offset < self::DAYS; ++$offset) {
            $index = $todayIndex + $offset;
            $dateValue = $dates[$index] ?? null;
            if (!\is_string($dateValue)) {
                break;
            }
            $max = $this->num($daily['temperature_2m_max'][$index] ?? null);
            $min = $this->num($daily['temperature_2m_min'][$index] ?? null);
            if (null === $max || null === $min) {
                continue;
            }

            $date = new \DateTimeImmutable($dateValue, $timezone);
            $code = (int) ($daily['weather_code'][$index] ?? 0);
            $windMax = $this->num($daily['wind_speed_10m_max'][$index] ?? null) ?? 0.0;
            $gustMax = $this->num($daily['wind_gusts_10m_max'][$index] ?? null);
            $uv = $this->num($daily['uv_index_max'][$index] ?? null);
            $sunrise = $this->moment($daily['sunrise'][$index] ?? null, $timezone);
            $sunset = $this->moment($daily['sunset'][$index] ?? null, $timezone);
            $parts = $this->dayParts($date, $hours);

            $days[] = new DayForecast(
                date: $dateValue,
                weekdayShort: WeatherFormat::weekdayShort((int) $date->format('w')),
                stripLabel: $dateValue === $today ? 'Сегодня' : $date->format('j'),
                title: $this->dayTitle($date, $dateValue, $today, $tomorrow),
                condition: WeatherFormat::capitalize(WeatherCodes::label($code)),
                icon: WeatherCodes::icon($code, true),
                max: WeatherFormat::temperature($max),
                min: WeatherFormat::temperature($min),
                windMax: WeatherFormat::windSpeed($windMax).' м/с',
                gustMax: null === $gustMax ? null : WeatherFormat::windSpeed($gustMax).' м/с',
                sunrise: $sunrise?->format('H:i'),
                sunset: $sunset?->format('H:i'),
                daylight: null !== $sunrise && null !== $sunset ? WeatherFormat::daylight($sunrise, $sunset) : null,
                uv: null === $uv ? null : (int) round($uv).', '.WeatherFormat::uvLabel($uv),
                moon: MoonPhase::name($date->setTime(12, 0)),
                precipitation: WeatherCodes::isPrecipitation($code),
                snow: WeatherCodes::isSnow($code),
                tempMax: $max,
                tempMin: $min,
                windMaxMs: $windMax,
                parts: $parts,
            );
        }

        return $days;
    }

    /**
     * @param array<string, array{temperature: float, apparent: float, humidity: int, probability: ?int, precipitation: float, code: int, pressure: float, wind: float, direction: float, gust: float, uv: float, isDay: bool}> $hours
     *
     * @return list<DayPart>
     */
    private function dayParts(\DateTimeImmutable $date, array $hours): array
    {
        $slots = [
            'Утром' => $date->setTime(9, 0),
            'Днём' => $date->setTime(15, 0),
            'Вечером' => $date->setTime(21, 0),
            'Ночью' => $date->modify('+1 day')->setTime(3, 0),
        ];

        $parts = [];
        foreach ($slots as $name => $moment) {
            $point = $hours[$moment->format('Y-m-d\TH:i')] ?? null;
            if (null === $point && 'Ночью' === $name) {
                $point = $hours[$date->setTime(23, 0)->format('Y-m-d\TH:i')] ?? null;
            }
            if (null === $point) {
                continue;
            }
            $parts[] = new DayPart(
                name: $name,
                temperature: WeatherFormat::temperature($point['temperature']),
                apparent: WeatherFormat::temperature($point['apparent']),
                condition: WeatherCodes::label($point['code']),
                icon: WeatherCodes::icon($point['code'], $point['isDay']),
                wind: WeatherFormat::windSpeed($point['wind']).' м/с, '.WeatherFormat::windShort($point['direction']),
                humidity: $point['humidity'].'%',
                pressure: (string) WeatherFormat::pressureMm($point['pressure']),
            );
        }

        return $parts;
    }

    /**
     * @param array<string, array{temperature: float, apparent: float, humidity: int, probability: ?int, precipitation: float, code: int, pressure: float, wind: float, direction: float, gust: float, uv: float, isDay: bool}> $hours
     */
    private function upcomingSummary(\DateTimeImmutable $hourStart, array $hours): string
    {
        for ($i = 0; $i <= 18; ++$i) {
            $moment = $hourStart->modify(sprintf('+%d hours', $i));
            $point = $hours[$moment->format('Y-m-d\TH:i')] ?? null;
            if (null === $point) {
                continue;
            }
            if (!WeatherCodes::isPrecipitation($point['code']) && $point['precipitation'] < 0.1) {
                continue;
            }
            $label = WeatherCodes::isPrecipitation($point['code']) ? WeatherCodes::label($point['code']) : 'осадки';
            if (0 === $i) {
                return WeatherFormat::capitalize($label);
            }

            return WeatherFormat::capitalize($label).' будет '.$this->partOfDay((int) $moment->format('G')).', около '.$moment->format('G:i');
        }

        return 'В ближайшие часы осадков не ожидается';
    }

    /**
     * @param array<string, array{temperature: float, apparent: float, humidity: int, probability: ?int, precipitation: float, code: int, pressure: float, wind: float, direction: float, gust: float, uv: float, isDay: bool}> $hours
     * @param list<DayForecast> $days
     *
     * @return list<TimelineEntry>
     */
    private function timeline(\DateTimeImmutable $hourStart, array $hours, array $days): array
    {
        $end = $hourStart->modify('+24 hours');
        $entries = [];

        for ($i = 0; $i < 24; ++$i) {
            $moment = $hourStart->modify(sprintf('+%d hours', $i));
            $point = $hours[$moment->format('Y-m-d\TH:i')] ?? null;
            if (null === $point) {
                continue;
            }
            $probability = null;
            if (null !== $point['probability'] && $point['probability'] >= 20 && (WeatherCodes::isPrecipitation($point['code']) || $point['probability'] >= 40)) {
                $probability = $point['probability'].'%';
            }
            $label = 0 === (int) $moment->format('G')
                ? WeatherFormat::weekdayShort((int) $moment->format('w')).', 00:00'
                : $moment->format('H:i');
            $caption = $label.': '.WeatherFormat::temperature($point['temperature']).', '.WeatherCodes::label($point['code']).', ощущается как '.WeatherFormat::temperature($point['apparent']);
            if (null !== $probability) {
                $caption .= '. Вероятность осадков '.$point['probability'].'%';
            }
            $entries[] = [
                'sort' => $moment->getTimestamp(),
                'entry' => new TimelineEntry(
                    kind: 'hour',
                    label: $label,
                    temperature: WeatherFormat::temperature($point['temperature']),
                    probability: $probability,
                    icon: WeatherCodes::icon($point['code'], $point['isDay']),
                    caption: $caption,
                ),
            ];
        }

        foreach ($days as $day) {
            foreach (['sunrise' => $day->sunrise, 'sunset' => $day->sunset] as $kind => $clock) {
                if (null === $clock) {
                    continue;
                }
                $moment = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $day->date.' '.$clock, $hourStart->getTimezone());
                if (false === $moment || $moment < $hourStart || $moment >= $end) {
                    continue;
                }
                $word = 'sunrise' === $kind ? 'Восход' : 'Закат';
                $entries[] = [
                    'sort' => $moment->getTimestamp(),
                    'entry' => new TimelineEntry(
                        kind: $kind,
                        label: $clock,
                        temperature: null,
                        probability: null,
                        icon: $kind,
                        caption: $word.' '.(int) $moment->format('j').' '.WeatherFormat::month((int) $moment->format('n')).' '.$clock,
                    ),
                ];
            }
        }

        usort($entries, static fn (array $left, array $right): int => $left['sort'] <=> $right['sort']);

        return array_map(static fn (array $row): TimelineEntry => $row['entry'], $entries);
    }

    /**
     * @param list<DayForecast> $days
     *
     * @return list<Outlook>
     */
    private function outlooks(string $summary, CurrentConditions $current, float $wind, array $days): array
    {
        $items = [
            new Outlook(
                'Сегодня',
                $summary.' · '.$current->temperature.' · '.mb_strtolower(WeatherFormat::windStrength($wind)).' ветер '.WeatherFormat::windSpeed($wind).' м/с',
            ),
        ];

        if (isset($days[1])) {
            $tomorrow = $days[1];
            $items[] = new Outlook(
                'Завтра',
                mb_strtolower($tomorrow->condition).' · '.$tomorrow->min.'…'.$tomorrow->max.' · ветер до '.$tomorrow->windMax,
            );
        }

        $week = $this->weekOutlook($days);
        if (null !== $week) {
            $items[] = $week;
        }

        return $items;
    }

    /**
     * @param list<DayForecast> $days
     */
    private function weekOutlook(array $days): ?Outlook
    {
        $slice = \array_slice($days, 1, 6);
        if (\count($slice) < 2) {
            return null;
        }

        $min = min(array_map(static fn (DayForecast $day): float => $day->tempMin, $slice));
        $max = max(array_map(static fn (DayForecast $day): float => $day->tempMax, $slice));
        $wind = max(array_map(static fn (DayForecast $day): float => $day->windMaxMs, $slice));
        $precip = array_values(array_filter($slice, static fn (DayForecast $day): bool => $day->precipitation));
        $snow = array_filter($precip, static fn (DayForecast $day): bool => $day->snow);
        $word = match (true) {
            [] === $precip => 'без существенных осадков',
            \count($snow) === \count($precip) => 'снег',
            \count($precip) >= 3 => 'дожди',
            default => 'временами осадки',
        };

        return new Outlook(
            'На неделе',
            $word.' · '.WeatherFormat::temperature($min).'…'.WeatherFormat::temperature($max).' · ветер до '.WeatherFormat::windSpeed($wind).' м/с',
        );
    }

    /**
     * @return list<FaqItem>
     */
    private function faq(
        Place $place,
        CurrentConditions $current,
        float $wind,
        float $direction,
        int $humidity,
        int $pressure,
        DayForecast $today,
        string $summary,
    ): array {
        $parts = [];
        foreach ($today->parts as $part) {
            $parts[$part->name] = $part;
        }
        $city = $place->locative;
        $condition = mb_strtolower($current->condition);

        $periodTemps = [];
        $periodWinds = [];
        $periodHumidity = [];
        foreach (['Утром' => 'утром', 'Днём' => 'днём', 'Вечером' => 'вечером', 'Ночью' => 'ночью'] as $name => $word) {
            if (!isset($parts[$name])) {
                continue;
            }
            $periodTemps[] = $word.' '.$parts[$name]->temperature;
            $periodWinds[] = $word.' '.$parts[$name]->wind;
            $periodHumidity[] = $word.' '.$parts[$name]->humidity;
        }

        $nowAnswer = sprintf(
            'Сейчас в %s %s, температура воздуха %s, ощущается как %s. Ветер %s м/с, %s, влажность %d%%, атмосферное давление %d мм рт. ст. %s.',
            $city,
            $condition,
            $current->temperature,
            $current->apparentTemperature,
            WeatherFormat::windSpeed($wind),
            WeatherFormat::windShort($direction),
            $humidity,
            $pressure,
            $summary,
        );

        $degreesAnswer = sprintf(
            'Сейчас в %s температура воздуха %s. Ощущается как %s, %s.',
            $city,
            $current->temperature,
            $current->apparentTemperature,
            $condition,
        );
        if ([] !== $periodTemps) {
            $degreesAnswer .= ' Температура '.implode(', ', $periodTemps).'.';
        }

        $windAnswer = sprintf(
            'Скорость ветра в %s сейчас %s м/с, %s.',
            $city,
            WeatherFormat::windSpeed($wind),
            WeatherFormat::windShort($direction),
        );
        if ([] !== $periodWinds) {
            $windAnswer .= ' '.WeatherFormat::capitalize(implode(', ', $periodWinds)).'.';
        }

        $humidityAnswer = sprintf('Сейчас влажность воздуха в %s составляет %d%%.', $city, $humidity);
        if ([] !== $periodHumidity) {
            $humidityAnswer .= ' Уровень влажности '.implode(', ', $periodHumidity).'.';
        }

        return [
            new FaqItem('Какая погода в '.$city.'?', $nowAnswer),
            new FaqItem('Сколько градусов в '.$city.'?', $degreesAnswer),
            new FaqItem('Какая скорость и направление ветра в '.$city.'?', $windAnswer),
            new FaqItem('Какая влажность воздуха в '.$city.'?', $humidityAnswer),
        ];
    }

    private function dayTitle(\DateTimeImmutable $date, string $dateValue, string $today, string $tomorrow): string
    {
        $pretty = (int) $date->format('j').' '.WeatherFormat::month((int) $date->format('n'));
        if ($dateValue === $today) {
            return 'Сегодня, '.$pretty;
        }
        if ($dateValue === $tomorrow) {
            return 'Завтра, '.$pretty;
        }

        return WeatherFormat::weekday((int) $date->format('w')).', '.$pretty;
    }

    private function partOfDay(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 12 => 'утром',
            $hour >= 12 && $hour < 18 => 'днём',
            $hour >= 18 && $hour < 23 => 'вечером',
            default => 'ночью',
        };
    }

    private function moment(mixed $value, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, $timezone);
        } catch (\Exception) {
            return null;
        }
    }

    private function num(mixed $value): ?float
    {
        if (\is_int($value) || \is_float($value)) {
            return (float) $value;
        }
        if (\is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
