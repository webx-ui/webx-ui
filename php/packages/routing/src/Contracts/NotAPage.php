<?php

declare(strict_types=1);

namespace WebxUi\Routing\Contracts;

use WebxUi\Routing\RouteType;

/**
 * A route handler that never shows a page: it answers every address of its type with a redirect
 * (or a 410) instead.
 *
 * A site does this by binding its own handler over a module's — events that send the reader
 * straight to an external booking page, categories that are only a filter on the list. The rows
 * stay in the registry, since the addresses still have to answer; but anything that lists the
 * site's pages — the sitemap first of all — must not offer them as pages, and the only one that
 * knows is the handler. So the handler says it, here, rather than a list in some config that has
 * to be kept in step with the binding:
 *
 *     final class EventRedirect implements RouteHandler, NotAPage { ... }
 *     $this->app->bind(EventHandler::class, EventRedirect::class);
 *
 * A marker, with nothing to implement: the question is asked of the type, not of one address
 * ({@see RouteType::servesPages()}).
 */
interface NotAPage {}
