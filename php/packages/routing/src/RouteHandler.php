<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What answers once the registry has decided whose address this is.
 *
 * The handler gets the entity already loaded and whatever was left of the path. Publication is
 * its business, not the registry's: the entity carries that state already, and a second copy of
 * it in `routes` would be a copy that drifts (§2, decision 9).
 */
interface RouteHandler
{
    /**
     * @param  string  $tail  What was left of the path after the matched row; '' for an exact hit.
     */
    public function handle(Request $request, object $entity, string $tail): Response;
}
