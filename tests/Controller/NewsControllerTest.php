<?php

namespace App\Tests\Controller;

use App\DataFixtures\NewsFixtures;
use App\Entity\News;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class NewsControllerTest extends WebTestCase
{
    private static bool $databaseReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();
        if (!self::$databaseReady) {
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $output = new NullOutput();
            $application->run(new ArrayInput([
                'command' => 'doctrine:database:create',
                '--if-not-exists' => true,
            ]), $output);
            $migrate = $application->run(new ArrayInput([
                'command' => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
            ]), $output);
            if (0 !== $migrate) {
                self::fail('Не удалось применить миграции в тестовой базе.');
            }
            self::$databaseReady = true;
        }

        $entities = static::getContainer()->get(EntityManagerInterface::class);
        $entities->getConnection()->executeStatement('TRUNCATE TABLE news RESTART IDENTITY');
        (new NewsFixtures())->load($entities);
        self::ensureKernelShutdown();
    }

    public function testNewsListShowsFirstPageAndLoadsTheNextOnes(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/news');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Новости погоды');
        self::assertCount(20, $crawler->filter('.news-card'));
        self::assertSame('11.10.26', $crawler->filter('.news-card time')->first()->text());
        $preview = (string) $crawler->filter('.news-card img')->first()->attr('src');
        self::assertStringStartsWith('/storage/news/', $preview);
        self::assertFileExists(dirname(__DIR__, 2).'/public'.$preview);
        self::assertSame('desc', $crawler->filter('#news-sort option[selected]')->attr('value'));
        self::assertSelectorExists('.top-wide .top-bar > .brand');
        self::assertSelectorExists('.top-bar .site-nav');
        self::assertSelectorNotExists('.top-inner .nav');
        self::assertSelectorExists('.news-sort label.sr-only');
        self::assertSelectorNotExists('.news-sort label:not(.sr-only)');
        self::assertSelectorExists('.sort-menu');
        $secondPageName = $crawler->filter('.news-card h2')->eq(19)->text();

        $client->xmlHttpRequest('GET', '/news?page=2');
        self::assertResponseIsSuccessful();
        $secondPage = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($secondPage['hasMore']);
        self::assertSame(20, substr_count($secondPage['html'], 'class="news-card"'));
        self::assertStringNotContainsString($secondPageName, $secondPage['html']);

        $client->xmlHttpRequest('GET', '/news?page=3');
        $thirdPage = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($thirdPage['hasMore']);
        self::assertSame(20, substr_count($thirdPage['html'], 'class="news-card"'));

        $client->xmlHttpRequest('GET', '/news?page=4');
        $emptyPage = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($emptyPage['hasMore']);
        self::assertSame('', $emptyPage['html']);
    }

    public function testNewsListSortsByDateAscending(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/news?sort=asc');

        self::assertResponseIsSuccessful();
        self::assertSame('asc', $crawler->filter('#news-sort option[selected]')->attr('value'));
        self::assertSame('13.08.26', $crawler->filter('.news-card time')->first()->text());

        $client->xmlHttpRequest('GET', '/news?sort=asc&page=2');
        $secondPage = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($secondPage['hasMore']);
        self::assertStringNotContainsString('13.08.26', $secondPage['html']);
    }

    public function testNewsPageShowsPreviewTitleDescriptionAndGallery(): void
    {
        $client = static::createClient();
        $news = $this->newsWithImages(true);
        $crawler = $client->request('GET', '/news/'.$news->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $news->getName());
        self::assertSelectorTextContains('time', $news->getCreatedAt()->format('d.m.y'));
        self::assertSame($news->getPreview(), $crawler->filter('.news-cover')->attr('src'));
        $lead = explode("\n\n", $news->getDescription())[0];
        self::assertStringContainsString($lead, $crawler->filter('.news-description')->text());
        $images = $news->getImages();
        self::assertNotNull($images);
        self::assertCount(3, $crawler->filter('.gallery-slide'));
        self::assertSame($images[0], $crawler->filter('.gallery-slide img')->first()->attr('src'));
        self::assertSelectorExists('[data-carousel-next]');
        self::assertCount(3, $crawler->filter('.gallery-dot'));
        self::assertSame('Фотография 1 из 3', $crawler->filter('.gallery-dot')->first()->attr('aria-label'));
        self::assertSame('', trim($crawler->filter('.gallery-dot')->first()->text()));
        self::assertGreaterThanOrEqual(2, $crawler->filter('.news-description p')->count());
    }

    public function testNewsWithoutImagesOmitsTheGallery(): void
    {
        $client = static::createClient();
        $news = $this->newsWithImages(false);

        $client->request('GET', '/news/'.$news->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $news->getName());
        self::assertSelectorTextContains('time', $news->getCreatedAt()->format('d.m.y'));
        self::assertSelectorNotExists('.gallery');
    }

    public function testMissingNewsReturnsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/news/999999');

        self::assertResponseStatusCodeSame(404);
    }

    private function newsWithImages(bool $withImages): News
    {
        $entities = static::getContainer()->get(EntityManagerInterface::class);
        $query = $entities->createQueryBuilder()
            ->select('news')
            ->from(News::class, 'news')
            ->setMaxResults(1);

        if ($withImages) {
            $query->andWhere('news.images IS NOT NULL');
        } else {
            $query->andWhere('news.images IS NULL');
        }

        $news = $query->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(News::class, $news);

        return $news;
    }
}
