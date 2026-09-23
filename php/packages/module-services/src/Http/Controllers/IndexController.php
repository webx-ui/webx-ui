<?php

declare(strict_types=1);

namespace WebxUi\Services\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Services\Rendering\Catalogue;
use WebxUi\Services\Rendering\Views;

/**
 * The index of the services: `{prefix}`, every visible category with its services, and the
 * services no visible category lists at the end (§4.4). No pagination — a site has dozens of
 * services, not thousands, and a catalogue split over pages is one nobody reads to the end.
 *
 * A route rather than an entity: there is nothing to edit here. `Reserved` asks the router, so
 * the address is closed to pages without anybody being told to close it.
 */
class IndexController
{
    public function __construct(
        private readonly Views $views,
        private readonly Catalogue $catalogue,
    ) {}

    public function __invoke(): Response
    {
        $categories = $this->catalogue->categories();
        $uncategorised = $this->catalogue->uncategorised();

        $this->catalogue->pushItemList([
            ...$categories->flatMap(static fn ($category) => $category->services)->all(),
            ...$uncategorised->all(),
        ]);

        return response($this->views->make('index', [
            'categories' => $categories,
            'uncategorised' => $uncategorised,
        ])->render());
    }
}
