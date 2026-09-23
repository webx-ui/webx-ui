<?php

declare(strict_types=1);

namespace WebxUi\Blog\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blog\Models\Tag;
use WebxUi\Blog\Rendering\Feed;
use WebxUi\Blog\Rendering\Views;
use WebxUi\Blog\Seo\TagSource;
use WebxUi\Routing\RouteHandler;

/**
 * A tag page: everything filed under one word (§5).
 *
 * Nothing about the index is decided here. Whether the page carries `noindex` is worked out
 * while the `<head>` is being printed, by {@see TagSource} — which has to ask the SEO module
 * whether a rule covers this address (§12), and that is a question about the request rather
 * than about the tag.
 */
class TagHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly Feed $feed,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Tag || ! $entity->isVisible()) {
            throw new NotFoundHttpException;
        }

        return response($this->views->make('tag', [
            'tag' => $entity,
            'articles' => $this->feed->withTag($entity),
        ])->render());
    }
}
