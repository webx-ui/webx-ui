<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Blog\Rendering\Feed;
use WebxUi\Blog\Rendering\Views;

/**
 * `{prefix}/rss` — the last twenty articles (§5).
 *
 * `<category>` is the main rubric, which is the first one in the article's list (§2.6): the same
 * rubric that goes in the breadcrumbs and in "more in this rubric", so that a reader following
 * the feed and a reader on the site are told the same thing about where an article belongs.
 *
 * The count is fixed rather than configurable. A feed is not a listing somebody browses — it is
 * what a reader's client polls, and twenty is the number at which a client that has been away
 * for a week still catches up.
 */
class RssController
{
    private const ITEMS = 20;

    public function __construct(
        private readonly Views $views,
        private readonly Feed $feed,
    ) {}

    public function __invoke(Request $request): Response
    {
        $body = $this->views->make('rss', [
            'articles' => $this->feed->latest(self::ITEMS),
            'url' => $request->url(),
        ])->render();

        return response($body, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
