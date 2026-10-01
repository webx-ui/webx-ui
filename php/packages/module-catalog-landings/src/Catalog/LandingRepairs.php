<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Catalog;

use WebxUi\Catalog\Events\FacetValueRetargeted;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\CatalogLandings\Models\Landing;

/**
 * What happens to a landing when a value of its set stops being itself (§9 of the landings spec).
 *
 * A merge replaces the value; a deletion drops it, marked `value_removed`; a facet left empty
 * drops out; a set left empty is `empty_set`, and one that became another landing's is
 * `duplicate` — both unpublished, the landing waiting for an editor. The save goes through the
 * model, so the journal holds the new set with the mark and the publication beside it — the
 * reason in the same row.
 *
 * A property in the bin is not an event of values: its facet is gone from the registry, and
 * {@see sweep()} drops it when the landing is next counted ({@see LandingCounter}) — the storefront
 * reads the set without it at once.
 */
final class LandingRepairs
{
    public function __construct(private readonly Facets $facets) {}

    public function handle(FacetValueRetargeted $event): void
    {
        $this->retarget($event->facetKey, $event->from, $event->to);
    }

    public function retarget(string $facetKey, string $from, ?string $to): void
    {
        // The key as JSON writes it narrows the table to the landings that may hold it; the set
        // itself decides.
        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string) json_encode($facetKey)).'%';

        $landings = Landing::withTrashed()->where('filters', 'like', $needle)->orderBy('id')->get();

        foreach ($landings as $landing) {
            $set = $landing->set();
            $values = $set->all()[$facetKey]['values'] ?? [];

            if (! in_array($from, $values, true)) {
                continue;
            }

            $this->apply($landing, $set->retarget($facetKey, $from, $to), $to === null ? Landing::VALUE_REMOVED : null);
        }
    }

    /**
     * The facets the registry no longer has dropped from the set. True when the set changed.
     */
    public function sweep(Landing $landing): bool
    {
        $set = $landing->set();
        $known = $set->known($this->facets);

        if ($known->equals($set)) {
            return false;
        }

        $this->apply($landing, $known, Landing::VALUE_REMOVED);

        return true;
    }

    /**
     * The landing with a new set: marked by what broke, and unpublished when what is left is no
     * landing at all — nothing, or another landing's set.
     */
    private function apply(Landing $landing, LandingSet $set, ?string $reason): void
    {
        $landing->filters = $set->all();

        if ($set->isEmpty()) {
            $landing->attention = Landing::EMPTY_SET;
            $landing->is_published = false;
        } elseif ($this->taken($landing, $set)) {
            $landing->attention = Landing::DUPLICATE;
            $landing->is_published = false;
        } elseif ($reason !== null) {
            $landing->attention = $reason;
        }

        $landing->save();
    }

    private function taken(Landing $landing, LandingSet $set): bool
    {
        return Landing::withTrashed()
            ->where('filters_hash', $set->hash($landing->category_id))
            ->whereKeyNot($landing->getKey())
            ->exists();
    }
}
