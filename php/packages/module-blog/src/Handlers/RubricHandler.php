<?php

declare(strict_types=1);

namespace WebxUi\Blog\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Rendering\Feed;
use WebxUi\Blog\Rendering\Views;
use WebxUi\Routing\RouteHandler;
use WebxUi\Seo\Rendering\Seo;

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
        private readonly Seo $seo,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Rubric || ! $entity->isVisible()) {
            throw new NotFoundHttpException;
        }

        $articles = $this->feed->inRubric($entity);

        // What is on this page of the rubric rather than what the rubric is, so it is the
        // handler's to say and not the model's (§17.2 of the SEO spec). Positions run on from
        // the pages before, the way a reader counts them.
        if ($articles->isNotEmpty()) {
            $first = (int) $articles->firstItem();

            $this->seo->push([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => collect($articles->items())->values()->map(static fn (Article $article, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $first + $i,
                    'url' => $article->url(),
                ])->all(),
            ]);
        }

        return response($this->views->make('rubric', [
            'rubric' => $entity,
            'articles' => $articles,
        ])->render());
    }
}
