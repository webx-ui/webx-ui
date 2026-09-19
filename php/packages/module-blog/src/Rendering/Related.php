<?php

declare(strict_types=1);

namespace WebxUi\Blog\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Blog\Models\Article;

/**
 * What to read next (§8).
 *
 * Two halves, and the order between them is the decision: the articles an editor pinned to this
 * one come first and always, and the rest is filled in. Fully manual is the version that stops
 * being filled in after the hundredth article; fully automatic is the version that cannot be
 * overruled on the one article where it matters.
 *
 * The automatic half is "most tags in common, then the main rubric", published only, minus this
 * article and minus whatever is already pinned. Tags first because they are the sharper signal:
 * two articles sharing three tags are about the same thing, while two articles in "News" share
 * a filing cabinet.
 */
final class Related
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return Collection<int, Article>
     */
    public function for(Article $article): Collection
    {
        $pinned = $article->related()->published()->get();

        $wanted = $this->limit() - $pinned->count();

        if ($wanted <= 0) {
            return $pinned;
        }

        /** @var list<int> $exclude */
        $exclude = [$article->getKey(), ...$pinned->modelKeys()];

        return $pinned->concat($this->suggest($article, $exclude, $wanted))->values();
    }

    /**
     * How many are worked out beyond the pinned ones. Zero leaves the pinned list alone, which
     * is what a site that wants nothing invented says.
     */
    public function limit(): int
    {
        return max(0, (int) $this->config->get('webx-blog.related', 3));
    }

    /**
     * @param  list<int>  $exclude
     * @return Collection<int, Article>
     */
    private function suggest(Article $article, array $exclude, int $wanted): Collection
    {
        $tagIds = $article->tags()->pluck('tags.id')->all();
        $rubric = $article->mainRubric();

        if ($tagIds === [] && $rubric === null) {
            return new Collection;
        }

        // One query for both signals rather than one each and a merge in PHP: the rubric half
        // has to know which ids the tag half already took, and ordering by the count in the
        // database is the only version of that which stays right when the tag half comes back
        // short. The ids are cast to int before they reach the string — they come from the
        // database, but a subquery built by concatenation is not a place to take that on trust.
        $shared = $tagIds === []
            ? '0 as shared_tags'
            : '(select count(*) from article_tag where article_tag.article_id = articles.id and article_tag.tag_id in ('
                .implode(',', array_map(intval(...), $tagIds))
                .')) as shared_tags';

        /** @var Collection<int, Article> $articles */
        $articles = Article::query()
            ->published()
            ->whereKeyNot($exclude)
            ->when(
                $rubric !== null,
                fn ($query) => $query->where(
                    fn ($inner) => $inner
                        ->whereHas('rubrics', fn ($r) => $r->whereKey($rubric?->getKey()))
                        ->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds)),
                ),
                fn ($query) => $query->whereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds)),
            )
            ->select('articles.*')
            ->selectRaw($shared)
            ->with(['cover', 'rubrics'])
            ->orderByDesc('shared_tags')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($wanted)
            ->get();

        return $articles;
    }
}
