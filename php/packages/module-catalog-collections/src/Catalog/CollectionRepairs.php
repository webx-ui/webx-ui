<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Catalog;

use WebxUi\Catalog\Events\FacetValueRetargeted;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * What happens to a collection when a value of its set stops being itself (§3.5).
 *
 * A merge moves it to the new value without a word. A deletion drops the value and marks the
 * collection; a set left empty also turns it off, or "by label" would become the whole catalogue.
 * A set that was empty from the start is legal ("new") and never comes here.
 *
 * A manual collection does not read its set, so losing a value changes nothing on the site and
 * there is nothing to tell the manager. The save goes through the model: the journal holds it, and
 * the group of the cache moves.
 */
final class CollectionRepairs
{
    public function handle(FacetValueRetargeted $event): void
    {
        $this->retarget($event->facetKey, $event->from, $event->to);
    }

    public function retarget(string $facetKey, string $from, ?string $to): void
    {
        // The bin too: a collection restored later must not come back with a value that is gone.
        $collections = ProductCollection::withTrashed()->whereNotNull('filters')->orderBy('id')->get();

        foreach ($collections as $collection) {
            $set = $collection->set();

            if (! in_array($from, $set->all()[$facetKey]['values'] ?? [], true)) {
                continue;
            }

            $left = $set->retarget($facetKey, $from, $to);
            $collection->filters = $left->all();

            if ($to === null && $collection->source === ProductCollection::FILTER) {
                $collection->attention = ProductCollection::VALUE_DELETED;
                $collection->is_enabled = $collection->is_enabled && ! $left->isEmpty();
            }

            $collection->save();
        }
    }
}
