<?php

namespace App\DataFixtures;

use App\Entity\News;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class NewsFixtures extends Fixture
{
    /**
     * @var array<string, list<string>>
     */
    private const PHOTOS = [
        'sun' => ['sun-1.jpg', 'sun-2.jpg', 'sun-3.jpg'],
        'cloud' => ['cloud-1.jpg', 'cloud-2.jpg', 'cloud-3.jpg'],
        'rain' => ['rain-1.jpg', 'rain-2.jpg', 'rain-3.jpg'],
        'snow' => ['snow-1.jpg', 'snow-2.jpg', 'snow-3.jpg'],
        'storm' => ['storm-1.jpg', 'storm-2.jpg', 'storm-3.jpg'],
        'fog' => ['fog-1.jpg', 'fog-2.jpg', 'fog-3.jpg'],
        'wind' => ['wind-1.jpg', 'wind-2.jpg', 'wind-3.jpg'],
        'frost' => ['frost-1.jpg', 'frost-2.jpg', 'frost-3.jpg'],
    ];

    public function load(ObjectManager $manager): void
    {
        $stories = NewsCatalog::stories();
        if (60 !== count($stories)) {
            throw new \LogicException(sprintf('В каталоге %d новостей, ожидалось 60.', count($stories)));
        }

        $newest = new \DateTimeImmutable('2026-10-11 12:00:00');

        foreach ($stories as $index => $story) {
            $number = $index + 1;
            $createdAt = $newest->modify(sprintf('-%d days', count($stories) - 1 - $index));
            $photos = self::PHOTOS[$story['scene']];
            $preview = '/storage/news/'.$photos[$index % 3];
            $images = null;
            if (0 !== $number % 7) {
                $images = [];
                foreach ([0, 1, 2] as $offset) {
                    $images[] = '/storage/news/'.$photos[($index + $offset) % 3];
                }
            }

            $manager->persist(new News(
                $story['name'],
                $story['description'],
                $preview,
                $images,
                $createdAt,
                $createdAt->modify('+2 hours'),
            ));
        }

        $manager->flush();
    }
}
