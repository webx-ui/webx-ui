<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a module says about an address the registry has no row for, before it becomes a 404.
 *
 * The registry holds where things are, not where they were or how else they could be spelled.
 * Two answers only the module that owns the entities can give: a product at `/{slug}-{id}` with
 * a slug that is not its own is still that product (301 to the right spelling, with no alias
 * kept for every rename), and a deleted one is gone on purpose (301 somewhere useful, or 410).
 *
 * Asked in the order they were registered; the first that answers wins. Null means "not mine".
 * The path is the one the registry was asked about — normalised, without the language prefix.
 */
interface MissHandler
{
    public function miss(Request $request, string $locale, string $path): ?Response;
}
