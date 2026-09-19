<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Panel\ArticleForm;

/**
 * Throw away what is waiting and go back to what the site is showing (§10).
 *
 * The button that calls this is only offered while there is a difference to throw away, which
 * is the one state where the question has an answer worth asking: an article that was never
 * published has nothing behind its draft, and discarding it would leave an empty page with a
 * title.
 *
 * What is dropped is not lost: `saveDraft()` keeps a ring of autosaves, so the last few minutes
 * of writing are still in `entity_versions` even though no screen lists them.
 */
final class ArticleDraftController
{
    public function __invoke(Request $request, Article $article, ArticleForm $form): JsonResponse
    {
        $article->discardDraft();

        $id = $request->user()?->getAuthIdentifier();

        return ApiResponse::data($form->describe(
            $article->refresh()->loadMissing(['routes', 'rubrics', 'tags', 'author', 'cover']),
            is_int($id) ? $id : null,
        ));
    }
}
