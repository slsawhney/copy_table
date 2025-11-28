-- Schema: Query for Both Local and Remote Databases (Only for Postgres)
-- We are expecting 4 tables: feeds, instagram_sources, tiktok_sources, posts already on the Production database.
-- So we need not create them on Production database.
-- We will create them only on Local database only (if doesn't exist').

-- 1) feeds
DROP TABLE IF EXISTS public.feeds CASCADE;
CREATE TABLE public.feeds (
  id           BIGSERIAL PRIMARY KEY,
  name         TEXT NOT NULL,
  ext_prod_id  BIGINT NOT NULL,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_feeds_ext_prod_id UNIQUE (ext_prod_id)
);

-- 2) instagram_sources (1:1 with feeds)
DROP TABLE IF EXISTS public.instagram_sources;
CREATE TABLE public.instagram_sources (
  feed_id     BIGINT PRIMARY KEY,
  name        TEXT NOT NULL,
  fan_count   INTEGER NOT NULL DEFAULT 0,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT fk_instagram_sources_feed
  FOREIGN KEY (feed_id) REFERENCES public.feeds(id) ON DELETE CASCADE,
  CONSTRAINT uq_instagram_sources_feed_id UNIQUE (feed_id)
);

-- 3) tiktok_sources (1:1 with feeds)
DROP TABLE IF EXISTS public.tiktok_sources;
CREATE TABLE public.tiktok_sources (
   feed_id     BIGINT PRIMARY KEY,
   name        TEXT NOT NULL,
   fan_count   INTEGER NOT NULL DEFAULT 0,
   created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
   updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
   CONSTRAINT fk_tiktok_sources_feed
   FOREIGN KEY (feed_id) REFERENCES public.feeds(id) ON DELETE CASCADE,
   CONSTRAINT uq_tiktok_sources_feed_id UNIQUE (feed_id)
);

-- 4) posts (many:1 feeds)
DROP TABLE IF EXISTS public.posts;
CREATE TABLE public.posts (
  id         BIGSERIAL PRIMARY KEY,
  feed_id    BIGINT NOT NULL,
  url        TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT fk_posts_feed
      FOREIGN KEY (feed_id) REFERENCES public.feeds(id) ON DELETE CASCADE,
  CONSTRAINT uq_posts_feed_id_url UNIQUE (feed_id, url)
);

-- indexes
CREATE INDEX idx_posts_feed_id ON public.posts(feed_id);

-- Dummy data query (Replica of Production Database)
-- For Local test only.
-- Insert or upsert a feed (ext_prod_id must be unique)
INSERT INTO public.feeds (name, ext_prod_id)
VALUES ('Test Feed A', 1001)
ON CONFLICT ON CONSTRAINT uq_feeds_ext_prod_id DO UPDATE
SET name = EXCLUDED.name, updated_at = now()
RETURNING *;

-- Link Instagram and TikTok sources (1:1 per feed)
INSERT INTO public.instagram_sources (feed_id, name, fan_count)
VALUES (1, 'insta_a', 12345)
ON CONFLICT ON CONSTRAINT uq_instagram_sources_feed_id DO UPDATE
SET name = EXCLUDED.name, fan_count = EXCLUDED.fan_count, updated_at = now();

INSERT INTO public.tiktok_sources (feed_id, name, fan_count)
VALUES (1, 'tiktok_a', 45678)
ON CONFLICT ON CONSTRAINT uq_tiktok_sources_feed_id DO UPDATE
SET name = EXCLUDED.name, fan_count = EXCLUDED.fan_count, updated_at = now();

-- Insert posts, ignore duplicates per (feed_id, url)
INSERT INTO public.posts (feed_id, url)
VALUES
(1, 'https://example.com/p/1'),
(1, 'https://example.com/p/2')
ON CONFLICT ON CONSTRAINT uq_posts_feed_id_url DO NOTHING;
