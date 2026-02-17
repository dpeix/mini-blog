<?php

namespace App\Command;

use App\Repository\ArticleRepository;
use App\Service\ArticleCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-likes',
    description: 'Sync article like counters from Redis to database',
)]
class SyncLikesCommand extends Command
{
    public function __construct(
        private ArticleCacheService $cacheService,
        private EntityManagerInterface $em,
        private ArticleRepository $repo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->cacheService->syncLikesToDatabase($this->em, $this->repo);

        $io->success(sprintf('%d article(s) synced from Redis to database.', $count));

        return Command::SUCCESS;
    }
}
