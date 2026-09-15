<?php

declare(strict_types=1);

namespace WebxUi\Routing\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Routing\Resolver;

/**
 * The fallback route's whole body.
 *
 * A class rather than a closure, and that is not a style preference: a closure in a route cannot
 * be serialised, so `route:cache` — which every deploy runs — would refuse the whole application.
 */
class ResolveController
{
    public function __construct(private readonly Resolver $resolver) {}

    public function __invoke(Request $request): Response
    {
        return $this->resolver->resolve($request);
    }
}
