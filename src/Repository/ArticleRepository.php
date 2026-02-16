<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * @return Article[]
     */
    public function searchByTitle(string $query): array
    {
        return $this->createQueryBuilder('a')
            ->where('LOWER(a.title) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
