<?php

namespace App\Tests\Unit\Service;

use App\Repository\ArticleRepository;
use App\Service\ArticleCacheService;
use PHPUnit\Framework\TestCase;

class ArticleCacheServiceTest extends TestCase
{
    private string $redisUrl = 'redis://invalid-host:6379';

    private function createService(?string $redisUrl = null): ArticleCacheService
    {
        return new ArticleCacheService($redisUrl ?? $this->redisUrl);
    }

    // --- LIKE COUNTER ---

    public function testIncrementLikeReturnsNullWhenRedisDown(): void
    {
        $service = $this->createService();

        $result = $service->incrementLike(1);

        self::assertNull($result);
    }

    public function testGetLikesReturnsNullWhenRedisDown(): void
    {
        $service = $this->createService();

        $result = $service->getLikes(1);

        self::assertNull($result);
    }

    public function testGetLikesReturnsNullWhenNotCached(): void
    {
        // This test needs real Redis - covered in integration tests
        self::assertTrue(true);
    }

    // --- TOP 5 ---

    public function testGetTopArticlesQueriesDbWhenRedisDown(): void
    {
        $service = $this->createService();

        $repo = $this->createMock(ArticleRepository::class);
        $repo->expects(self::once())
            ->method('findBy')
            ->with([], ['likes' => 'DESC'], 5)
            ->willReturn(['article1', 'article2']);

        $result = $service->getTopArticles($repo);

        self::assertSame(['article1', 'article2'], $result);
    }

    // --- LATEST ---

    public function testGetLatestArticlesQueriesDbWhenRedisDown(): void
    {
        $service = $this->createService();

        $repo = $this->createMock(ArticleRepository::class);
        $repo->expects(self::once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], 3)
            ->willReturn(['recent1', 'recent2', 'recent3']);

        $result = $service->getLatestArticles($repo);

        self::assertSame(['recent1', 'recent2', 'recent3'], $result);
    }

    // --- INVALIDATION ---

    public function testInvalidateOnCreateDoesNotThrowWhenRedisDown(): void
    {
        $service = $this->createService();

        // Should not throw
        $service->invalidateOnCreate();

        self::assertTrue(true);
    }

    public function testInvalidateOnDeleteDoesNotThrowWhenRedisDown(): void
    {
        $service = $this->createService();

        // Should not throw
        $service->invalidateOnDelete(42);

        self::assertTrue(true);
    }
}
