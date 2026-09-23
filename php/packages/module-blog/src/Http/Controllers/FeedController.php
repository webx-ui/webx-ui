<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Rendering\Feed;
use WebxUi\Blog\Rendering\Views;

/**
 * The front page of the blog: `{prefix}`, with `?page=2` under it (§2.11).
 *
 * A route rather than an entity, because it is not one — there is nothing to edit, nothing to
 * give an address to and nothing to put in a bin. The registry closes the address for pages of
 * its own accord, because `Reserved` asks the router, so an editor who tries to put a page at
 * `/blog` is told rather than left with a page that exists and never answers.
 *
 * Page two is a query string and not a path (§2.12). The alternative is two hundred rows in the
 * registry where one belongs, and the resolver drops the query anyway.
 */
class FeedController
{
    public function __construct(
        private readonly Views $views,
        private readonly Feed $feed,
    ) {}

    public function __invoke(Request $request): Response
    {
        return response($this->views->make('feed', [
            'articles' => $this->feed->all(),
            // The menu of the blog. Handed over rather than left to the view to query, so that
            // a site rewriting the view does not have to know how a hidden rubric is spelled.
            'rubrics' => Rubric::query()->visible()->inMenuOrder()->get(),
        ])->render());
    }
}
