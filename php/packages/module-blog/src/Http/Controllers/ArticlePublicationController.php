<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blog\Http\Resources\ArticleResource;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Panel\Instant;

/**
 * On the site and off it — and, for an article, "on the site from Tuesday" as well (§7).
 *
 * The date is the whole of scheduling. There is no queue and no scheduler: `published_at` in
 * the future means the article is waiting, and every listing in the module asks `published()`,
 * which compares it with now at the moment somebody reads. Backdating is the same field used
 * the other way.
 *
 * It travels as an argument to `publish()` rather than through the draft, because the draft
 * deliberately cannot carry it: saving a draft must never put anything on the site. The
 * alternative — publish, then save the date — would leave a version in the history stamped with
 * the wrong one.
 */
final class ArticlePublicationController
{
    public function publish(Request $request, Article $article): JsonResponse
    {
        $validated = $request->validate(['at' => ['sometimes', 'nullable', 'date']]);

        $article->publish(
            $this->author($request),
            EntityVersion::SOURCE_PANEL,
            at: Instant::from($validated['at'] ?? null),
        );

        return ApiResponse::data(new ArticleResource($this->loaded($article->refresh())));
    }

    /**
     * Off the site, and nothing else touched: what was being prepared is still being prepared,
     * and the address stays in the registry. An address released on unpublishing would be taken
     * by the next article called the same thing, and putting this one back would be a move.
     */
    public function unpublish(Article $article): JsonResponse
    {
        $article->unpublish();

        return ApiResponse::data(new ArticleResource($this->loaded($article->refresh())));
    }

    private function loaded(Article $article): Article
    {
        return $article->loadMissing(['routes', 'rubrics', 'tags', 'author', 'cover']);
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
