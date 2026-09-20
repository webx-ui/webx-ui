<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blog\Http\Resources\ArticleResource;
use WebxUi\Blog\Models\Article;

/**
 * Out of the bin.
 *
 * Bound by hand rather than by the route, because a deleted article is invisible to the model
 * binding every other endpoint here uses — and this is the one endpoint whose whole subject is
 * an article nobody can see.
 *
 * The address comes back by itself, or the restore is refused because somebody has taken the
 * spelling in the meantime: `OnConflict::Fail` makes that a 422 under the slug, which is the
 * right answer. An article quietly restored to `remont-2` is worse than one that says the place
 * is occupied.
 */
final class ArticleRestoreController
{
    public function __invoke(int $article): JsonResponse
    {
        $trashed = Article::withTrashed()->findOrFail($article);

        $trashed->restore();

        return ApiResponse::data(new ArticleResource(
            $trashed->refresh()->loadMissing(['routes', 'rubrics', 'tags', 'author', 'cover']),
        ));
    }
}
