<?php

declare(strict_types=1);

namespace WebxUi\Blog\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blog\Models\Author;
use WebxUi\Blog\Rendering\Feed;
use WebxUi\Blog\Rendering\Views;
use WebxUi\Routing\RouteHandler;

/**
 * The page of an author: who they are and everything of theirs on the site (§19.3).
 *
 * The address stays in the registry whatever the flags say (§19.1, decision 5), so this is what
 * closes it: a site without author pages, a profile that did not ask for one — a 404, as for an
 * article that is not out yet. An open author with nothing published still gets the page, out of
 * the index ({@see Author::seoFallback()}).
 */
class AuthorHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly Feed $feed,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Author || ! $entity->isOpen()) {
            throw new NotFoundHttpException;
        }

        return response($this->views->make('author', [
            'author' => $entity,
            'articles' => $this->feed->byAuthor($entity),
        ])->render());
    }
}
