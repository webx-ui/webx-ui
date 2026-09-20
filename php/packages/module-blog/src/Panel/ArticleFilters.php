<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Localization\Locales;

/**
 * What the four dropdowns over the list can be set to (§10).
 *
 * They travel with the page of articles rather than being fetched one by one, for the reason a
 * list of columns travels with the submissions of a form: the screen cannot draw its own
 * filters without them, and three more round trips before the first row appears is three more
 * chances to draw a half-built screen.
 *
 * Only what narrows anything. A rubric with no articles in it is offered — it is a section of
 * the blog either way, and an editor filtering by it to find it empty has learned something —
 * but a tag nobody has used is not: tags are entered by the hundred from the article's own form
 * (§2.8), and a dropdown of every string anybody ever typed is not a filter.
 */
final class ArticleFilters
{
    /**
     * As many tags as a dropdown is worth reading. Past that the answer is the search box, and
     * the tags screen is where a blog with more than this goes to be raked through (§10).
     */
    private const TAGS = 200;

    public function __construct(private readonly Locales $locales) {}

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function all(): array
    {
        $locale = $this->locales->current();

        return [
            'rubrics' => Rubric::query()
                ->inMenuOrder()
                ->get()
                ->map(fn (Rubric $rubric): array => $this->named($rubric, $locale))
                ->all(),
            'tags' => Tag::query()
                ->has('articles')
                ->orderBy('id')
                ->limit(self::TAGS)
                ->get()
                ->map(fn (Tag $tag): array => $this->named($tag, $locale))
                ->all(),
            // The administrators who have actually written something, not every account: a
            // filter that offers somebody with no articles can only ever answer "none".
            'authors' => CmsUser::query()
                ->whereIn('id', Article::query()->withTrashed()->whereNotNull('author_id')->select('author_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (CmsUser $user): array => [
                    'id' => (int) $user->getKey(),
                    'title' => (string) $user->name,
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: int, title: string}
     */
    private function named(Rubric|Tag $entity, string $locale): array
    {
        $title = $entity->getTranslation('title', $locale);
        $slug = $entity->getTranslation('slug', $locale);

        return [
            'id' => (int) $entity->getKey(),
            // A rubric named in one language and not in another is still in the list: its
            // address is the name it has everywhere, and an unnamed row nobody can pick is
            // worse than a row named after its slug.
            'title' => is_string($title) && trim($title) !== '' ? $title : (string) $slug,
        ];
    }
}
