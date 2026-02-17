<?php

namespace App\Session;

use Psr\Log\LoggerInterface;

class FallbackSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private string $prefix;
    private int $ttl;
    private ?\Redis $redis = null;
    private bool $redisConnected = false;

    public function __construct(
        private string $redisUrl,
        private string $savePath,
        private ?LoggerInterface $logger = null,
        string $prefix = 'sf_s',
        ?int $ttl = null,
    ) {
        $this->prefix = $prefix;
        $this->ttl = $ttl ?? (int) \ini_get('session.gc_maxlifetime');

        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0777, true);
        }
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string
    {
        $redis = $this->getRedis();

        if ($redis !== null) {
            try {
                $data = $redis->get($this->prefix . $sessionId);

                if ($data !== false && $data !== '') {
                    return $data;
                }

                // Redis OK but no data — check filesystem for migration
                $filePath = $this->getFilePath($sessionId);
                if (is_file($filePath)) {
                    $fileData = (string) file_get_contents($filePath);
                    if ($fileData !== '') {
                        $redis->setEx($this->prefix . $sessionId, $this->ttl, $fileData);
                        unlink($filePath);

                        $this->logger?->info('Session migrated from filesystem to Redis.', [
                            'session_id' => substr($sessionId, 0, 8) . '...',
                        ]);

                        return $fileData;
                    }
                }

                return '';
            } catch (\RedisException $e) {
                $this->logger?->warning('Redis unavailable on session read, falling back to filesystem.', [
                    'session_id' => substr($sessionId, 0, 8) . '...',
                    'error' => $e->getMessage(),
                ]);
                $this->redisConnected = false;
            }
        }

        // Filesystem fallback
        $filePath = $this->getFilePath($sessionId);
        if (is_file($filePath)) {
            return (string) file_get_contents($filePath);
        }

        return '';
    }

    public function write(string $sessionId, string $data): bool
    {
        $redis = $this->getRedis();

        if ($redis !== null) {
            try {
                $redis->setEx($this->prefix . $sessionId, $this->ttl, $data);

                // Clean up filesystem fallback file if it exists
                $filePath = $this->getFilePath($sessionId);
                if (is_file($filePath)) {
                    unlink($filePath);
                }

                return true;
            } catch (\RedisException $e) {
                $this->logger?->warning('Redis unavailable on session write, falling back to filesystem.', [
                    'session_id' => substr($sessionId, 0, 8) . '...',
                    'error' => $e->getMessage(),
                ]);
                $this->redisConnected = false;
            }
        }

        return file_put_contents($this->getFilePath($sessionId), $data) !== false;
    }

    public function destroy(string $sessionId): bool
    {
        $redis = $this->getRedis();

        if ($redis !== null) {
            try {
                $redis->del($this->prefix . $sessionId);
            } catch (\RedisException) {
                $this->redisConnected = false;
            }
        }

        $filePath = $this->getFilePath($sessionId);
        if (is_file($filePath)) {
            unlink($filePath);
        }

        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        $count = 0;
        $files = glob($this->savePath . '/sess_*');

        if ($files) {
            $expiry = time() - $maxlifetime;
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $expiry) {
                    unlink($file);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function validateId(string $sessionId): bool
    {
        $redis = $this->getRedis();

        if ($redis !== null) {
            try {
                $data = $redis->get($this->prefix . $sessionId);
                if ($data !== false && $data !== '') {
                    return true;
                }
            } catch (\RedisException) {
                $this->redisConnected = false;
            }
        }

        return is_file($this->getFilePath($sessionId));
    }

    public function updateTimestamp(string $sessionId, string $data): bool
    {
        $redis = $this->getRedis();

        if ($redis !== null) {
            try {
                return $redis->expire($this->prefix . $sessionId, $this->ttl);
            } catch (\RedisException) {
                $this->redisConnected = false;
            }
        }

        $filePath = $this->getFilePath($sessionId);
        if (is_file($filePath)) {
            return touch($filePath);
        }

        return false;
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
            @$this->redis->connect($host, $port, 1.0); // 1 second timeout
            $this->redisConnected = true;

            return $this->redis;
        } catch (\RedisException $e) {
            $this->logger?->warning('Cannot connect to Redis, using filesystem sessions.', [
                'error' => $e->getMessage(),
            ]);
            $this->redis = null;
            $this->redisConnected = false;

            return null;
        }
    }

    private function getFilePath(string $sessionId): string
    {
        return $this->savePath . '/sess_' . $sessionId;
    }
}
