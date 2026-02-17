<?php

namespace App\Tests\Integration\Session;

use App\Session\FallbackSessionHandler;
use PHPUnit\Framework\TestCase;

class RedisSessionTest extends TestCase
{
    private string $redisUrl;
    private string $savePath;

    protected function setUp(): void
    {
        $this->redisUrl = $_ENV['REDIS_URL'] ?? 'redis://redis:6379';
        $this->savePath = sys_get_temp_dir() . '/integration_session_test_' . bin2hex(random_bytes(8));
        mkdir($this->savePath, 0777, true);

        // Verify Redis is available for integration tests
        $redis = new \Redis();
        $host = parse_url($this->redisUrl, PHP_URL_HOST) ?: 'redis';
        $port = (int) (parse_url($this->redisUrl, PHP_URL_PORT) ?: 6379);
        if (!@$redis->connect($host, $port, 1.0)) {
            self::markTestSkipped('Redis is not available.');
        }
        $redis->close();
    }

    protected function tearDown(): void
    {
        $files = glob($this->savePath . '/*');
        if ($files) {
            array_map('unlink', $files);
        }
        if (is_dir($this->savePath)) {
            rmdir($this->savePath);
        }
    }

    public function testSessionWriteAndReadViaRedis(): void
    {
        $handler = new FallbackSessionHandler($this->redisUrl, $this->savePath);

        $sessionId = 'integ_' . bin2hex(random_bytes(16));
        $data = '_sf2_attributes|a:1:{s:4:"user";s:7:"johndoe";}';

        $handler->write($sessionId, $data);
        $readData = $handler->read($sessionId);

        self::assertSame($data, $readData);

        // Verify NOT on filesystem
        self::assertFileDoesNotExist($this->savePath . '/sess_' . $sessionId);

        $handler->destroy($sessionId);
    }

    public function testSessionFallbackToFilesystemWhenRedisDown(): void
    {
        $handler = new FallbackSessionHandler('redis://invalid-host:6379', $this->savePath);

        $sessionId = 'integ_fallback_' . bin2hex(random_bytes(16));
        $data = '_sf2_attributes|a:1:{s:4:"test";s:8:"fallback";}';

        $result = $handler->write($sessionId, $data);

        self::assertTrue($result);
        self::assertFileExists($this->savePath . '/sess_' . $sessionId);
        self::assertSame($data, file_get_contents($this->savePath . '/sess_' . $sessionId));

        $readData = $handler->read($sessionId);
        self::assertSame($data, $readData);

        $handler->destroy($sessionId);
    }

    public function testSessionMigrationFromFilesystemToRedis(): void
    {
        $sessionId = 'integ_migrate_' . bin2hex(random_bytes(16));
        $data = '_sf2_attributes|a:1:{s:4:"user";s:5:"migre";}';

        // Step 1: Write to filesystem (simulate Redis was down)
        file_put_contents($this->savePath . '/sess_' . $sessionId, $data);

        // Step 2: Read with Redis available — should migrate
        $handler = new FallbackSessionHandler($this->redisUrl, $this->savePath);
        $readData = $handler->read($sessionId);

        self::assertSame($data, $readData);

        // Verify migrated to Redis
        $redis = new \Redis();
        $host = parse_url($this->redisUrl, PHP_URL_HOST) ?: 'redis';
        $port = (int) (parse_url($this->redisUrl, PHP_URL_PORT) ?: 6379);
        $redis->connect($host, $port);
        $storedInRedis = $redis->get('sf_s' . $sessionId);
        self::assertSame($data, $storedInRedis);
        $redis->close();

        // Verify filesystem file cleaned up
        self::assertFileDoesNotExist($this->savePath . '/sess_' . $sessionId);

        $handler->destroy($sessionId);
    }

    public function testDestroyRemovesFromBoth(): void
    {
        $handler = new FallbackSessionHandler($this->redisUrl, $this->savePath);

        $sessionId = 'integ_destroy_' . bin2hex(random_bytes(16));
        $data = 'some_data';

        $handler->write($sessionId, $data);
        // Also create filesystem file (simulate leftover)
        file_put_contents($this->savePath . '/sess_' . $sessionId, $data);

        $handler->destroy($sessionId);

        // Verify filesystem cleaned
        self::assertFileDoesNotExist($this->savePath . '/sess_' . $sessionId);

        // Verify Redis cleaned
        $redis = new \Redis();
        $host = parse_url($this->redisUrl, PHP_URL_HOST) ?: 'redis';
        $port = (int) (parse_url($this->redisUrl, PHP_URL_PORT) ?: 6379);
        $redis->connect($host, $port);
        self::assertFalse($redis->get('sf_s' . $sessionId));
        $redis->close();
    }
}
