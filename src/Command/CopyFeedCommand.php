<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\FeedSourceRepository;
use App\Repository\FeedTargetRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:copy-feed',
    description: 'Copy a feed (and related data) from Production to the local Local database'
)]
class CopyFeedCommand extends Command
{
    private const VALID_SOURCES = ['instagram', 'tiktok'];

    public function __construct(
        private readonly FeedSourceRepository $sourceRepo,   // Production.
        private readonly FeedTargetRepository $targetRepo    // Local.
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'feedId',
                InputArgument::REQUIRED,
                'Feed ID to copy from Production'
            )
            ->addOption(
                'only',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated sources to copy (instagram,tiktok)'
            )
            ->addOption(
                'include-posts',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of posts to copy',
                '0'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $feedId = (int)$input->getArgument('feedId');
        $onlyOpt = $input->getOption('only');
        $includePosts = (int)$input->getOption('include-posts');

        if ($feedId <= 0) {
            $output->writeln('<error>feedId must be a positive integer.</error>');
            return Command::INVALID;
        }
        if ($includePosts < 0) {
            $output->writeln('<error>--include-posts must be >= 0</error>');
            return Command::INVALID;
        }

        $only = null;
        if ($onlyOpt) {
            $only = array_filter(array_map('trim', explode(',', strtolower($onlyOpt))));
            foreach ($only as $source) {
                if (!in_array($source, self::VALID_SOURCES, true)) {
                    $output->writeln(sprintf(
                        '<error>Invalid source "%s". Allowed: %s</error>',
                        $source,
                        implode(',', self::VALID_SOURCES)
                    ));
                    return Command::INVALID;
                }
            }
            $only = array_values(array_unique($only));
        }

        // Fetch feed.
        $feed = $this->sourceRepo->fetchFeed($feedId);
        if (!$feed) {
            $output->writeln(sprintf('<error>Feed %d not found in Production.</error>', $feedId));
            return Command::FAILURE;
        }

        $copyInstagram = !$only || in_array('instagram', $only, true);
        $copyTiktok    = !$only || in_array('tiktok', $only, true);

        $transactionStarted = false;

        try {
            // Start transaction BEFORE other operations that can throw.
            $this->targetRepo->begin();
            $transactionStarted = true;

            // Fetch source data.
            $instagram = $copyInstagram ? $this->sourceRepo->fetchInstagramSource($feedId) : null;
            $tiktok    = $copyTiktok ? $this->sourceRepo->fetchTikTokSource($feedId) : null;
            $posts     = $includePosts > 0 ? $this->sourceRepo->fetchPosts($feedId, $includePosts) : [];

            // Upsert feed.
            $newFeedId = $this->targetRepo->upsertFeed($feed, $feedId);

            // Upsert sources.
            if ($copyInstagram && $instagram) {
                $this->targetRepo->upsertInstagramSource($newFeedId, $instagram);
            }
            if ($copyTiktok && $tiktok) {
                $this->targetRepo->upsertTikTokSource($newFeedId, $tiktok);
            }

            // Insert posts.
            if (!empty($posts)) {
                $this->targetRepo->insertPosts($newFeedId, $posts);
            }

            $this->targetRepo->commit();

            $copiedSources = array_filter([
                $copyInstagram && $instagram ? 'instagram' : null,
                $copyTiktok && $tiktok ? 'tiktok' : null,
            ]);

            $output->writeln(sprintf(
                '<info>Copied feed %d to dev feed %d. Sources: [%s]. Posts: %d.</info>',
                $feedId,
                $newFeedId,
                implode(',', $copiedSources) ?: 'none',
                count($posts)
            ));

            if (!empty($copiedSources) && (count($copiedSources) < 2)) {
                $available = [];
                if (!$copyInstagram && $this->sourceRepo->fetchInstagramSource($feedId)) {
                    $available[] = 'instagram';
                }
                if (!$copyTiktok && $this->sourceRepo->fetchTikTokSource($feedId)) {
                    $available[] = 'tiktok';
                }
                if (!empty($available)) {
                    $output->writeln(sprintf(
                        '<comment>Other sources available but not copied: %s</comment>',
                        implode(',', $available)
                    ));
                }
            }
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->targetRepo->rollback();
            }
            $output->writeln('<error>Copy failed: ' . $e->getMessage() . '</error>');
            if ($output->isVerbose()) {
                $output->writeln('<error>Stack trace: ' . $e->getTraceAsString() . '</error>');
            }
            return Command::FAILURE;
        }
    }
}
