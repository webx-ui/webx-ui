<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The handlers asked when an address matched nothing in the registry (§8, step 7).
 *
 * Classes rather than objects, made when asked: a miss is the rare request, and a handler's
 * dependencies — a model query, the view factory — have no business being built on every boot.
 */
class Misses
{
    /** @var list<class-string<MissHandler>> */
    private array $handlers = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<MissHandler>  $handler
     */
    public function register(string $handler): void
    {
        if (! in_array($handler, $this->handlers, true)) {
            $this->handlers[] = $handler;
        }
    }

    public function answer(Request $request, string $locale, string $path): ?Response
    {
        foreach ($this->handlers as $class) {
            /** @var MissHandler $handler */
            $handler = $this->container->make($class);
            $response = $handler->miss($request, $locale, $path);

            if ($response !== null) {
                return $response;
            }
        }

        return null;
    }
}
