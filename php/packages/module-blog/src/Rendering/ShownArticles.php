<?php

declare(strict_types=1);

namespace WebxUi\Blog\Rendering;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Blog\Models\Article;

/**
 * The articles the block "Latest articles" turned into cards a moment ago, for its template.
 *
 * The block's value is resolved before it is drawn — the cards, through the collection source —
 * and the template draws the blog's own card, which wants the models. Asking the feed again
 * gave the same articles with the same relations and the same addresses: every query twice a
 * render. Scoped, so a request or a job keeps only its own.
 */
final class ShownArticles
{
    /** @var array<int, Article> */
    private array $articles = [];

    /** @param  list<Article>  $articles */
    public function keep(array $articles): void
    {
        foreach ($articles as $article) {
            $this->articles[(int) $article->getKey()] = $article;
        }
    }

    /**
     * These articles, in this order — or null when one of them was not shown here, and the
     * caller asks the feed.
     *
     * @param  list<int>  $ids
     * @return EloquentCollection<int, Article>|null
     */
    public function take(array $ids): ?EloquentCollection
    {
        $found = [];

        foreach ($ids as $id) {
            if (! isset($this->articles[$id])) {
                return null;
            }

            $found[] = $this->articles[$id];
        }

        return new EloquentCollection($found);
    }
}
