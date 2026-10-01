<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Catalog;

use Illuminate\Contracts\Bus\Dispatcher;
use WebxUi\Catalog\Events\ProductsIndexed;
use WebxUi\CatalogLandings\Jobs\CountLandings;
use WebxUi\CatalogLandings\Models\Landing;

/**
 * A batch of the index reached the engine: the landings on its categories — and the ones on the
 * whole catalogue — wait for a count, and one job counts them (§6.5 of the landings spec). One
 * statement marks them; the batch's products are never read again.
 */
final class IndexedBases
{
    public function __construct(private readonly Dispatcher $bus) {}

    public function handle(ProductsIndexed $event): void
    {
        $categories = $event->categories;

        $marked = Landing::query()
            ->where(static function ($bases) use ($categories): void {
                $bases->whereNull('category_id');

                if ($categories !== []) {
                    $bases->orWhereIn('category_id', $categories);
                }
            })
            ->toBase()
            ->update(['counted_at' => null]);

        if ($marked > 0) {
            $this->bus->dispatch(new CountLandings);
        }
    }
}
