<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests\Fixtures;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Routing\Contracts\NotAPage;
use WebxUi\Routing\RouteHandler;

/**
 * What a site binds over a module's handler when the addresses of a type are only a way
 * somewhere else — a category that is a filter on the list, an event that is a booking page
 * on another site.
 */
class RedirectingHandler implements NotAPage, RouteHandler
{
    public function handle(Request $request, object $entity, string $tail): Response
    {
        return new RedirectResponse('/elsewhere', 301);
    }
}
