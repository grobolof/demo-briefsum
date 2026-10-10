<?php

namespace App\Repository;

use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<News>
 */
class NewsRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }

    /**
     * @return array{items: list<News>, hasMore: bool}
     */
    public function page(int $page, string $sort = 'desc'): array
    {
        $page = max(1, $page);
        $direction = 'asc' === $sort ? 'ASC' : 'DESC';

        /** @var list<News> $items */
        $items = $this->createQueryBuilder('news')
            ->orderBy('news.createdAt', $direction)
            ->addOrderBy('news.id', $direction)
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE + 1)
            ->getQuery()
            ->getResult();

        $hasMore = count($items) > self::PER_PAGE;

        return [
            'items' => array_slice($items, 0, self::PER_PAGE),
            'hasMore' => $hasMore,
        ];
    }
}
