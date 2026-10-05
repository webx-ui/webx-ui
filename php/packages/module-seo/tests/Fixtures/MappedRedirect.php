<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests\Fixtures;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Routing\Contracts\NotAPage;
use WebxUi\Routing\RouteHandler;

/**
 * What a site binds over the module's handler when it wants no pages of that type — every
 * address goes to a booking page on another site, say — and says so with {@see NotAPage}.
 */
class MappedRedirect implements NotAPage, RouteHandler
{
    public function handle(Request $request, object $entity, string $tail): Response
    {
        return new RedirectResponse('https://booking.example.test/', 301);
    }
}
