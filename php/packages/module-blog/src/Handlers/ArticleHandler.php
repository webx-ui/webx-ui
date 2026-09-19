<?php

declare(strict_types=1);

namespace WebxUi\Blog\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Rendering\Related;
use WebxUi\Blog\Rendering\Views;
use WebxUi\Routing\RouteHandler;

/**
 * What answers once the registry has decided that this address is an article.
 *
 * Publication is decided here rather than in the registry (§4): an article that is a draft, or
 * one dated next Tuesday, is a 404 to everybody — and the same article under a preview token is
 * shown as it will be, because the preview route of `module-blocks` has already laid the draft
 * over the columns by the time it reaches this.
 *
 * `isPublished()` and not `published_at !== null` is the whole of scheduling (§7). The one thing
 * worth remembering about it is that the answer changes without anything being written: an
 * article 404s at one minute past and answers at two.
 */
class ArticleHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly Related $related,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Article) {
            throw new NotFoundHttpException;
        }

        if (! $entity->isPublished() && PreviewGrant::of($request) === null) {
            throw new NotFoundHttpException;
        }

        $entity->loadMissing(['cover', 'author', 'rubrics', 'tags']);

        return response($this->views->make('article', [
            'article' => $entity,
            'rubric' => $entity->mainRubric(),
            'related' => $this->related->for($entity),
        ])->render());
    }
}
