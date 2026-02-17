<?php

namespace App\Tests\Unit\Session;

use App\Session\FallbackSessionHandler;
use PHPUnit\Framework\TestCase;

class FallbackSessionHandlerTest extends TestCase
{
    private string $savePath;

    protected function setUp(): void
    {
        $this->savePath = sys_get_temp_dir() . '/fallback_session_test_' . bin2hex(random_bytes(8));
        mkdir($this->savePath, 0777, true);
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

    private function createHandler(?string $redisUrl = null): FallbackSessionHandler
    {
        return new FallbackSessionHandler(
            $redisUrl ?? 'redis://invalid-host-that-does-not-exist:6379',
            $this->savePath,
        );
    }

    // --- BOOT RESILIENCE ---

    public function testConstructorDoesNotConnectToRedis(): void
    {
        // Must not throw even with an invalid Redis URL
        $handler = $this->createHandler('redis://this-host-does-not-exist:6379');

        self::assertInstanceOf(FallbackSessionHandler::class, $handler);
    }

    // --- READ ---

    public function testReadFallbackToFilesystemWhenRedisDown(): void
    {
        $handler = $this->createHandler();

        file_put_contents($this->savePath . '/sess_abc123', 'filesystem_data');

        $result = $handler->read('abc123');

        self::assertSame('filesystem_data', $result);
    }

    public function testReadReturnsEmptyWhenBothUnavailable(): void
    {
        $handler = $this->createHandler();

        $result = $handler->read('nonexistent');

        self::assertSame('', $result);
    }

    // --- WRITE ---

    public function testWriteFallbackToFilesystemWhenRedisDown(): void
    {
        $handler = $this->createHandler();

        $result = $handler->write('abc123', 'data');

        self::assertTrue($result);
        self::assertFileExists($this->savePath . '/sess_abc123');
        self::assertSame('data', file_get_contents($this->savePath . '/sess_abc123'));
    }

    // --- DESTROY ---

    public function testDestroySucceedsWhenRedisDown(): void
    {
        $handler = $this->createHandler();
        file_put_contents($this->savePath . '/sess_abc123', 'data');

        $result = $handler->destroy('abc123');

        self::assertTrue($result);
        self::assertFileDoesNotExist($this->savePath . '/sess_abc123');
    }

    // --- GC ---

    public function testGcCleansExpiredFiles(): void
    {
        $handler = $this->createHandler();

        $file = $this->savePath . '/sess_expired';
        file_put_contents($file, 'old');
        touch($file, time() - 3600);

        $file2 = $this->savePath . '/sess_fresh';
        file_put_contents($file2, 'new');

        $result = $handler->gc(1800);

        self::assertFileDoesNotExist($file);
        self::assertFileExists($file2);
        self::assertSame(1, $result);
    }

    // --- UPDATE TIMESTAMP ---

    public function testUpdateTimestampFallbackToFilesystem(): void
    {
        $handler = $this->createHandler();

        $file = $this->savePath . '/sess_abc123';
        file_put_contents($file, 'data');
        touch($file, time() - 100);
        $oldMtime = filemtime($file);

        clearstatcache();
        $result = $handler->updateTimestamp('abc123', 'data');

        clearstatcache();
        self::assertTrue($result);
        self::assertGreaterThan($oldMtime, filemtime($file));
    }
}
