<?php

namespace App\Repository;

use App\DB\DatabaseConnectionInterface;

class FeedSourceRepository
{
    public function __construct(private readonly DatabaseConnectionInterface $conn)
    {
    }

    // Command expects this
    public function fetchFeed(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM public.feeds WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // If your command uses ext_prod_id instead
    public function fetchFeedByExtProdId(int $extProdId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM public.feeds WHERE ext_prod_id = :ext_prod_id');
        $stmt->execute([':ext_prod_id' => $extProdId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function fetchInstagramSource(int $feedId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM public.instagram_sources WHERE feed_id = :feed_id');
        $stmt->execute([':feed_id' => $feedId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function fetchTikTokSource(int $feedId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM public.tiktok_sources WHERE feed_id = :feed_id');
        $stmt->execute([':feed_id' => $feedId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function fetchPosts(int $feedId, int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM public.posts WHERE feed_id = :feed_id ORDER BY id DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':feed_id', $feedId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
