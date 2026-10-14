<?php

declare(strict_types=1);

namespace WebxUi\Blog\Collections;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Blog\Rendering\ArticleQuery;

/**
 * The articles a `wx-collection` field shows: `{ "props": { "source": "blog" } }` — the block
 * "Latest articles" on a home page or a service page (§20).
 *
 * A thin layer over {@see ArticleQuery}. No markup of its own: `BlogPosting` belongs to the
 * article's page, and the same posting claimed by every page that lists it is noise.
 */
final class ArticlesSource implements CollectionSource
{
    public const KEY = 'blog';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.articles');
    }

    public function categories(): string
    {
        return 'blog/rubrics';
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return [];
    }

    public function supportsMarkup(): bool
    {
        return false;
    }

    public function permission(): string
    {
        return 'blog.articles.view';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(Selection $selection, string $locale): array
    {
        return (new ArticleQuery)->selected($selection)->locale($locale)->get();
    }
}
