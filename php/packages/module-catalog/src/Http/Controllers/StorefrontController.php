<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Catalog\Storefront\Storefront;
use WebxUi\Catalog\Storefront\Suggestions;

/**
 * The two pages of the storefront that are routes rather than rows of the registry (§4): the root
 * of the catalogue, when it is switched on, and the search, which is always there and always
 * `noindex`. Both take a filter's tail, like a category. And the suggestions of the search, the
 * HTML its box shows while typing (WIDGETS W6.5).
 */
final class StorefrontController
{
    public function __construct(private readonly Storefront $storefront) {}

    public function root(Request $request): Response
    {
        return $this->storefront->root($request, (string) $request->route('tail', ''));
    }

    public function search(Request $request): Response
    {
        return $this->storefront->search($request, (string) $request->route('tail', ''));
    }

    public function suggest(Request $request, Suggestions $suggestions): Response
    {
        return $suggestions->answer($request);
    }
}
