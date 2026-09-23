<?php

declare(strict_types=1);

namespace WebxUi\Blog\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Rendering\Feed;
use WebxUi\Blog\Rendering\Views;
use WebxUi\Routing\RouteHandler;

/**
 * A rubric page: a heading, an introduction and the articles filed under it (§5).
 *
 * Hidden is a 404 here and nothing more (§3). The articles it holds go on answering at their
 * own addresses, because they are not its property: an article is in three rubrics at once, and
 * putting one of them away must not take the article off the site with it.
 */
class RubricHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly Feed $feed,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Rubric || ! $entity->is_visible) {
            throw new NotFoundHttpException;
        }

        return response($this->views->make('rubric', [
            'rubric' => $entity,
            'articles' => $this->feed->inRubric($entity),
        ])->render());
    }
}
