<?php

namespace App\Repository;

use App\DB\DatabaseConnectionInterface;
use InvalidArgumentException;

class FeedTargetRepository
{
    public function __construct(private readonly DatabaseConnectionInterface $conn)
    {
    }

    public function begin(): void
    {
        $this->conn->beginTransaction();
    }

    public function commit(): void
    {
        $this->conn->commit();
    }

    public function rollback(): void
    {
        $this->conn->rollBack();
    }

    public function upsertFeed(array $feed, int $sourceFeedId): int
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO public.feeds (name, ext_prod_id)
             VALUES (:name, :ext_prod_id)
             ON CONFLICT ON CONSTRAINT uq_feeds_ext_prod_id DO UPDATE
             SET name = EXCLUDED.name, updated_at = now()
             RETURNING id'
        );
        $stmt->execute([
            ':name' => $feed['name'],
            ':ext_prod_id' => $sourceFeedId,
        ]);

        $row = $stmt->fetch();
        if (!$row || !isset($row['id'])) {
            throw new InvalidArgumentException('Failed to upsert feed');
        }
        return (int)$row['id'];
    }

    public function upsertInstagramSource(int $feedId, array $source): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO public.instagram_sources (feed_id, name, fan_count)
             VALUES (:feed_id, :name, :fan_count)
             ON CONFLICT ON CONSTRAINT uq_instagram_sources_feed_id DO UPDATE
             SET name = EXCLUDED.name, fan_count = EXCLUDED.fan_count, updated_at = now()'
        );
        $stmt->execute([
            ':feed_id' => $feedId,
            ':name' => $source['name'],
            ':fan_count' => (int)$source['fan_count'],
        ]);
    }

    public function upsertTikTokSource(int $feedId, array $source): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO public.tiktok_sources (feed_id, name, fan_count)
             VALUES (:feed_id, :name, :fan_count)
             ON CONFLICT ON CONSTRAINT uq_tiktok_sources_feed_id DO UPDATE
             SET name = EXCLUDED.name, fan_count = EXCLUDED.fan_count, updated_at = now()'
        );
        $stmt->execute([
            ':feed_id' => $feedId,
            ':name' => $source['name'],
            ':fan_count' => (int)$source['fan_count'],
        ]);
    }

    public function insertPosts(int $feedId, array $posts): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO public.posts (feed_id, url)
             VALUES (:feed_id, :url)
             ON CONFLICT ON CONSTRAINT uq_posts_feed_id_url DO NOTHING'
        );

        foreach ($posts as $post) {
            $stmt->execute([
                ':feed_id' => $feedId,
                ':url' => $post['url'],
            ]);
        }
    }
}
