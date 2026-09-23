<?php

declare(strict_types=1);

namespace WebxUi\Blog\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Rendering\Feed;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads to know what this blog is about (§13).
 *
 * One resource, and it is the front page: the articles a reader would see right now, newest
 * first, with their addresses and their rubrics. An agent asked to "write something about
 * warranties" needs that before it needs anything else — half of what it is for is finding out
 * that the blog published exactly that in March, and the other half is picking up how the blog
 * writes: how long a lead is, whether titles are questions, which rubrics carry what.
 *
 * Published only, and in the reader's order rather than the editor's. `articles_list` is the
 * editor's view — drafts, what is waiting, what is in the bin — and this is deliberately not a
 * second one of those.
 */
final class BlogResources
{
    /** Enough to read the blog's voice off, short enough to be one message. */
    private const LATEST = 30;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'blog://feed',
                'Blog feed',
                'The last articles on the site, newest first: title, lead, address, rubrics, tags and the day '
                .'each went out. What the blog is about and how it writes — read it before writing for it.',
                fn (): array => $this->feed(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function feed(): array
    {
        /** @var Locales $locales */
        $locales = $this->container->make(Locales::class);

        /** @var Config $config */
        $config = $this->container->make('config');

        $articles = $this->container->make(Feed::class)->latest(self::LATEST);

        // The tags are not in the listing's eager load — a card on the site does not print them
        // — and an agent reading what the blog is about wants them more than it wants a cover.
        $articles->loadMissing('tags');

        $prefix = (string) $config->get('webx-blog.prefix', 'blog');

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            // Where the blog lives, because "how-to-choose" says nothing about whether it is
            // under `/blog/` or at the root of the site (§4).
            'prefix' => $prefix,
            'feed_url' => $prefix === '' ? null : url($prefix),
            'count' => $articles->count(),
            'articles' => $articles->map(fn (Article $article): array => $this->entry($article))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(Article $article): array
    {
        $locale = $this->container->make(Locales::class)->current();

        return [
            'id' => (int) $article->getKey(),
            'title' => (string) $article->getTranslation('title', $locale),
            'lead' => (string) $article->getTranslation('lead', $locale),
            'url' => $article->url($locale),
            'published_at' => $article->published_at?->toAtomString(),
            'pinned' => (bool) $article->pinned,
            // In the order that makes the first one the main one (§2.6).
            'rubrics' => $article->rubrics
                ->map(static fn (Rubric $rubric): string => (string) $rubric->getTranslation('title', $locale))
                ->values()
                ->all(),
            'tags' => $article->tags
                ->map(static fn ($tag): string => (string) $tag->getTranslation('title', $locale))
                ->values()
                ->all(),
        ];
    }
}
