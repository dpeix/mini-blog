<?php

namespace App\Tests\Integration\Service;

use App\Service\ArticleCacheService;
use PHPUnit\Framework\TestCase;

class ArticleCacheServiceIntegrationTest extends TestCase
{
    private string $redisUrl;
    private \Redis $redis;
    private ArticleCacheService $service;

    protected function setUp(): void
    {
        $this->redisUrl = $_ENV['REDIS_URL'] ?? 'redis://redis:6379';

        $this->redis = new \Redis();
        $host = parse_url($this->redisUrl, PHP_URL_HOST) ?: 'redis';
        $port = (int) (parse_url($this->redisUrl, PHP_URL_PORT) ?: 6379);

        if (!@$this->redis->connect($host, $port, 1.0)) {
            self::markTestSkipped('Redis is not available.');
        }

        // Clean test keys
        $this->cleanRedisKeys();

        $this->service = new ArticleCacheService($this->redisUrl);
    }

    protected function tearDown(): void
    {
        $this->cleanRedisKeys();
        $this->redis->close();
    }

    private function cleanRedisKeys(): void
    {
        $keys = $this->redis->keys('article:*');
        if ($keys) {
            $this->redis->del(...$keys);
        }
    }

    // --- LIKES ---

    public function testIncrementLikeStoresInRedis(): void
    {
        // Init likes to 10
        $this->service->initLikes(1, 10);

        $result = $this->service->incrementLike(1);

        self::assertSame(11, $result);
        self::assertSame('11', $this->redis->get('article:likes:1'));
    }

    public function testIncrementLikeMultipleTimes(): void
    {
        $this->service->initLikes(1, 0);

        $this->service->incrementLike(1);
        $this->service->incrementLike(1);
        $result = $this->service->incrementLike(1);

        self::assertSame(3, $result);
    }

    public function testGetLikesReturnsValue(): void
    {
        $this->redis->set('article:likes:5', '42');

        $result = $this->service->getLikes(5);

        self::assertSame(42, $result);
    }

    public function testGetLikesReturnsNullWhenNotCached(): void
    {
        $result = $this->service->getLikes(999);

        self::assertNull($result);
    }

    public function testInitLikesDoesNotOverwrite(): void
    {
        $this->redis->set('article:likes:1', '50');

        $this->service->initLikes(1, 10);

        self::assertSame('50', $this->redis->get('article:likes:1'));
    }

    // --- INVALIDATION ---

    public function testIncrementLikeInvalidatesTop5(): void
    {
        $this->redis->set('article:top5', '["cached"]');
        $this->service->initLikes(1, 5);

        $this->service->incrementLike(1);

        self::assertFalse($this->redis->get('article:top5'));
    }

    public function testInvalidateOnCreateDeletesBothKeys(): void
    {
        $this->redis->set('article:top5', '["cached"]');
        $this->redis->set('article:latest', '["cached"]');

        $this->service->invalidateOnCreate();

        self::assertFalse($this->redis->get('article:top5'));
        self::assertFalse($this->redis->get('article:latest'));
    }

    public function testInvalidateOnDeleteRemovesAllKeys(): void
    {
        $this->redis->set('article:top5', '["cached"]');
        $this->redis->set('article:latest', '["cached"]');
        $this->redis->set('article:likes:42', '10');

        $this->service->invalidateOnDelete(42);

        self::assertFalse($this->redis->get('article:top5'));
        self::assertFalse($this->redis->get('article:latest'));
        self::assertFalse($this->redis->get('article:likes:42'));
    }
}
