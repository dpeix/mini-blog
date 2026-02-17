<?php

namespace App\Tests\Unit\Command;

use App\Command\SyncLikesCommand;
use App\Repository\ArticleRepository;
use App\Service\ArticleCacheService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SyncLikesCommandTest extends TestCase
{
    public function testSyncOutputsCount(): void
    {
        $cacheService = $this->createMock(ArticleCacheService::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(ArticleRepository::class);

        $cacheService->expects(self::once())
            ->method('syncLikesToDatabase')
            ->with($em, $repo)
            ->willReturn(3);

        $command = new SyncLikesCommand($cacheService, $em, $repo);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('3', $tester->getDisplay());
    }

    public function testSyncWithZeroArticles(): void
    {
        $cacheService = $this->createMock(ArticleCacheService::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(ArticleRepository::class);

        $cacheService->method('syncLikesToDatabase')->willReturn(0);

        $command = new SyncLikesCommand($cacheService, $em, $repo);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('0', $tester->getDisplay());
    }
}
