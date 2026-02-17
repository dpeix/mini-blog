<?php

namespace App\Service;

use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ArticleCacheService
{
    private const TTL = 3600;
    private const KEY_TOP5 = 'article:top5';
    private const KEY_LATEST = 'article:latest';
    private const KEY_LIKES_PREFIX = 'article:likes:';

    private ?\Redis $redis = null;
    private bool $redisConnected = false;

    public function __construct(
        private string $redisUrl,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function incrementLike(int $articleId): ?int
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return null;
        }

        try {
            $newCount = $redis->incr(self::KEY_LIKES_PREFIX . $articleId);
            $redis->del(self::KEY_TOP5);

            return (int) $newCount;
        } catch (\RedisException $e) {
            $this->logger?->warning('Redis error on incrementLike.', ['error' => $e->getMessage()]);
            $this->redisConnected = false;

            return null;
        }
    }

    public function initLikes(int $articleId, int $currentLikes): void
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return;
        }

        try {
            if (!$redis->exists(self::KEY_LIKES_PREFIX . $articleId)) {
                $redis->set(self::KEY_LIKES_PREFIX . $articleId, $currentLikes);
            }
        } catch (\RedisException) {
            $this->redisConnected = false;
        }
    }

    public function getLikes(int $articleId): ?int
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return null;
        }

        try {
            $value = $redis->get(self::KEY_LIKES_PREFIX . $articleId);

            return $value !== false ? (int) $value : null;
        } catch (\RedisException) {
            $this->redisConnected = false;

            return null;
        }
    }

    /**
     * @return \App\Entity\Article[]
     */
    public function getTopArticles(ArticleRepository $repo): array
    {
        $ids = $this->getCachedIds(self::KEY_TOP5);

        if ($ids !== null) {
            $articles = $repo->findBy(['id' => $ids]);
            // Restore order by likes DESC
            usort($articles, fn ($a, $b) => $b->getLikes() <=> $a->getLikes());

            if ($articles !== []) {
                return $articles;
            }
        }

        $articles = $repo->findBy([], ['likes' => 'DESC'], 5);
        $this->cacheIds(self::KEY_TOP5, $articles);

        return $articles;
    }

    /**
     * @return \App\Entity\Article[]
     */
    public function getLatestArticles(ArticleRepository $repo): array
    {
        $ids = $this->getCachedIds(self::KEY_LATEST);

        if ($ids !== null) {
            $articles = $repo->findBy(['id' => $ids]);
            // Restore order by createdAt DESC
            usort($articles, fn ($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

            if ($articles !== []) {
                return $articles;
            }
        }

        $articles = $repo->findBy([], ['createdAt' => 'DESC'], 3);
        $this->cacheIds(self::KEY_LATEST, $articles);

        return $articles;
    }

    /**
     * @return int[]|null
     */
    private function getCachedIds(string $key): ?array
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return null;
        }

        try {
            $cached = $redis->get($key);
            if ($cached !== false) {
                return json_decode($cached, true);
            }
        } catch (\RedisException) {
            $this->redisConnected = false;
        }

        return null;
    }

    /**
     * @param \App\Entity\Article[] $articles
     */
    private function cacheIds(string $key, array $articles): void
    {
        $redis = $this->getRedis();
        if ($redis === null || !$this->redisConnected) {
            return;
        }

        try {
            $ids = array_map(fn ($a) => $a->getId(), $articles);
            $redis->setEx($key, self::TTL, json_encode($ids));
        } catch (\RedisException) {
            $this->redisConnected = false;
        }
    }

    public function invalidateOnCreate(): void
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return;
        }

        try {
            $redis->del(self::KEY_TOP5, self::KEY_LATEST);
        } catch (\RedisException) {
            $this->redisConnected = false;
        }
    }

    public function invalidateOnDelete(int $articleId): void
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return;
        }

        try {
            $redis->del(self::KEY_TOP5, self::KEY_LATEST, self::KEY_LIKES_PREFIX . $articleId);
        } catch (\RedisException) {
            $this->redisConnected = false;
        }
    }

    /**
     * @return int Number of articles synced
     */
    public function syncLikesToDatabase(EntityManagerInterface $em, ArticleRepository $repo): int
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return 0;
        }

        try {
            $keys = $redis->keys(self::KEY_LIKES_PREFIX . '*');
        } catch (\RedisException) {
            $this->redisConnected = false;

            return 0;
        }

        $count = 0;
        foreach ($keys as $key) {
            $articleId = (int) str_replace(self::KEY_LIKES_PREFIX, '', $key);

            try {
                $likes = (int) $redis->get($key);
            } catch (\RedisException) {
                continue;
            }

            $article = $repo->find($articleId);
            if ($article !== null) {
                $article->setLikes($likes);
                $count++;
            }

            try {
                $redis->del($key);
            } catch (\RedisException) {
                // Ignore — will be cleaned up next sync
            }
        }

        if ($count > 0) {
            $em->flush();
        }

        return $count;
    }

    private function getRedis(): ?\Redis
    {
        if ($this->redisConnected) {
            return $this->redis;
        }

        try {
            $this->redis = new \Redis();
            $host = parse_url($this->redisUrl, PHP_URL_HOST) ?: 'localhost';
            $port = (int) (parse_url($this->redisUrl, PHP_URL_PORT) ?: 6379);
            @$this->redis->connect($host, $port, 1.0);
            $this->redisConnected = true;

            return $this->redis;
        } catch (\RedisException $e) {
            $this->logger?->warning('Cannot connect to Redis for article cache.', [
                'error' => $e->getMessage(),
            ]);
            $this->redis = null;
            $this->redisConnected = false;

            return null;
        }
    }
}
