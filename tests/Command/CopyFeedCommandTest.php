<?php

namespace App\Tests\Command;

use App\Command\CopyFeedCommand;
use App\Repository\FeedSourceRepository;
use App\Repository\FeedTargetRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;
use Exception;

class CopyFeedCommandTest extends TestCase
{
    private FeedSourceRepository|MockObject $sourceRepo;
    private FeedTargetRepository|MockObject $targetRepo;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->sourceRepo = $this->createMock(FeedSourceRepository::class);
        $this->targetRepo = $this->createMock(FeedTargetRepository::class);

        $application = new Application();
        $application->add(new CopyFeedCommand($this->sourceRepo, $this->targetRepo));
        $command = $application->find('app:copy-feed');
        $this->commandTester = new CommandTester($command);
    }

    public function testCopyFeedWithAllSourcesNoPosts(): void
    {
        // Arrange
        $feedData = ['id' => 123, 'name' => 'Alice Influencer'];
        $instagramData = ['feed_id' => 123, 'name' => 'alice_ig', 'fan_count' => 1000];
        $tiktokData = ['feed_id' => 123, 'name' => 'alice_tt', 'fan_count' => 2000];

        $this->sourceRepo->method('fetchFeed')->with(123)->willReturn($feedData);
        $this->sourceRepo->method('fetchInstagramSource')->with(123)->willReturn($instagramData);
        $this->sourceRepo->method('fetchTikTokSource')->with(123)->willReturn($tiktokData);
        // No posts requested by default (include-posts=0), so fetchPosts is not called.

        $this->targetRepo->expects($this->once())->method('begin');
        $this->targetRepo->expects($this->once())->method('upsertFeed')
            ->with($feedData, 123)->willReturn(456);
        $this->targetRepo->expects($this->once())->method('upsertInstagramSource')
            ->with(456, $instagramData);
        $this->targetRepo->expects($this->once())->method('upsertTikTokSource')
            ->with(456, $tiktokData);
        // Posts are not included, so insertPostsIfNew should NOT be called.
        $this->targetRepo->expects($this->never())->method('insertPosts');
        $this->targetRepo->expects($this->once())->method('commit');

        // Act
        $exitCode = $this->commandTester->execute(['feedId' => 123]);

        // Assert
        $this->assertSame(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Copied feed 123 to dev feed 456', $output);
        $this->assertStringContainsString('Sources: [instagram,tiktok]', $output);
        $this->assertStringContainsString('Posts: 0', $output);
    }

    public function testCopyFeedInstagramOnlyWithPosts(): void
    {
        // Arrange
        $feedData = ['id' => 123, 'name' => 'Bob Creator'];
        $instagramData = ['feed_id' => 123, 'name' => 'bob_ig', 'fan_count' => 50];
        $postsData = [
            ['id' => 1, 'url' => 'https://example.com/post/1'],
            ['id' => 2, 'url' => 'https://example.com/post/2'],
            ['id' => 3, 'url' => 'https://example.com/post/3'],
        ];

        $this->sourceRepo->method('fetchFeed')->with(123)->willReturn($feedData);
        $this->sourceRepo->method('fetchInstagramSource')->with(123)->willReturn($instagramData);
        $this->sourceRepo->method('fetchTikTokSource')->with(123)->willReturn(null);
        $this->sourceRepo->method('fetchPosts')->with(123, 3)->willReturn($postsData);

        $this->targetRepo->expects($this->once())->method('begin');
        $this->targetRepo->expects($this->once())->method('upsertFeed')
            ->with($feedData, 123)->willReturn(789);
        $this->targetRepo->expects($this->once())->method('upsertInstagramSource')
            ->with(789, $instagramData);
        $this->targetRepo->expects($this->never())->method('upsertTikTokSource');
        $this->targetRepo->expects($this->once())->method('insertPosts')
            ->with(789, $postsData);
        $this->targetRepo->expects($this->once())->method('commit');

        // Act
        $exitCode = $this->commandTester->execute([
            'feedId' => 123,
            '--only' => 'instagram',
            '--include-posts' => 3,
        ]);

        // Assert
        $this->assertSame(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Sources: [instagram]', $output);
        $this->assertStringContainsString('Posts: 3', $output);
    }

    public function testCopyFeedMultipleSourcesInOnly(): void
    {
        // Arrange
        $feedData = ['id' => 456, 'name' => 'Charlie Multi'];
        $instagramData = ['feed_id' => 456, 'name' => 'charlie_ig', 'fan_count' => 500];
        $tiktokData = ['feed_id' => 456, 'name' => 'charlie_tt', 'fan_count' => 750];

        $this->sourceRepo->method('fetchFeed')->with(456)->willReturn($feedData);
        $this->sourceRepo->method('fetchInstagramSource')->with(456)->willReturn($instagramData);
        $this->sourceRepo->method('fetchTikTokSource')->with(456)->willReturn($tiktokData);

        $this->targetRepo->expects($this->once())->method('begin');
        $this->targetRepo->expects($this->once())->method('upsertFeed')
            ->with($feedData, 456)->willReturn(999);
        $this->targetRepo->expects($this->once())->method('upsertInstagramSource')
            ->with(999, $instagramData);
        $this->targetRepo->expects($this->once())->method('upsertTikTokSource')
            ->with(999, $tiktokData);
        $this->targetRepo->expects($this->never())->method('insertPosts');
        $this->targetRepo->expects($this->once())->method('commit');

        // Act
        $exitCode = $this->commandTester->execute([
            'feedId' => 456,
            '--only' => 'instagram,tiktok',
        ]);

        // Assert
        $this->assertSame(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Sources: [instagram,tiktok]', $output);
    }

    public function testFeedNotFoundInProduction(): void
    {
        // Arrange
        $this->sourceRepo->method('fetchFeed')->with(999)->willReturn(null);
        $this->targetRepo->expects($this->never())->method('begin');

        // Act
        $exitCode = $this->commandTester->execute(['feedId' => 999]);

        // Assert
        $this->assertSame(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Feed 999 not found in Production', $output);
    }

    public function testInvalidFeedId(): void
    {
        // Act
        $exitCode = $this->commandTester->execute(['feedId' => 0]);

        // Assert
        $this->assertSame(2, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('feedId must be a positive integer', $output);
    }

    public function testInvalidSourceInOnly(): void
    {
        // Act
        $exitCode = $this->commandTester->execute([
            'feedId' => 123,
            '--only' => 'facebook',
        ]);

        // Assert
        $this->assertSame(2, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Invalid source "facebook"', $output);
        $this->assertStringContainsString('Allowed: instagram,tiktok', $output);
    }

    public function testNegativeIncludePosts(): void
    {
        // Act
        $exitCode = $this->commandTester->execute([
            'feedId' => 123,
            '--include-posts' => -5,
        ]);

        // Assert
        $this->assertSame(2, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('--include-posts must be >= 0', $output);
    }

    public function testDatabaseTransactionRollbackOnFailure(): void
    {
        // Arrange
        $feedData = ['id' => 123, 'name' => 'Zoe Error'];
        $this->sourceRepo->method('fetchFeed')->with(123)->willReturn($feedData);

        // Begin should be called, then an exception is thrown during upsertFeed
        $this->targetRepo->expects($this->once())->method('begin');
        $this->targetRepo->expects($this->once())->method('upsertFeed')
            ->with($feedData, 123)
            ->willThrowException(new Exception('boom'));
        $this->targetRepo->expects($this->once())->method('rollback');
        $this->targetRepo->expects($this->never())->method('commit');

        // Act
        $exitCode = $this->commandTester->execute(['feedId' => 123]);

        // Assert
        $this->assertSame(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Copy failed: boom', $output);
    }
}
