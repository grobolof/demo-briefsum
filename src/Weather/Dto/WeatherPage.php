<?php

namespace App\Weather\Dto;

final readonly class WeatherPage
{
    /**
     * @param list<Fact>          $facts
     * @param list<Outlook>       $outlook
     * @param list<Fact>          $wellbeing
     * @param list<TimelineEntry> $timeline
     * @param list<DayForecast>   $days
     * @param list<FaqItem>       $faq
     */
    public function __construct(
        public string $city,
        public string $cityIn,
        public string $country,
        public string $localTime,
        public CurrentConditions $current,
        public array $facts,
        public array $outlook,
        public array $wellbeing,
        public array $timeline,
        public array $days,
        public array $faq,
    ) {
    }
}
