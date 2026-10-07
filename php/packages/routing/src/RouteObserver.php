<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\Exceptions\PathRejected;

/**
 * Keeps an entity's addresses in step with the entity.
 *
 * On `created` rather than `saving`, because `SlugId` has no key to put on the end until the
 * insert has happened (§7). Everything the observer writes goes through `RouteSync`, which
 * writes quietly — a suffix landing back in the slug must not start the observer again.
 *
 * The address itself comes back on `updated`, not `restored`: a restore is a `save()` of a model
 * whose `deleted_at` went back to null, so `updated` has already run — including the undo that
 * puts the entity back in the bin when the address it wants was taken while it was gone.
 * `restored` only brings back the trail of former addresses the bin kept.
 */
class RouteObserver
{
    public function __construct(private readonly RouteSync $sync) {}

    public function created(Model $entity): void
    {
        try {
            $this->sync->sync($entity);
        } catch (PathRejected $rejected) {
            // The insert has already happened — that is the price of `created`. A refused
            // address means the save did not happen at all, so the row goes away again: an
            // entity left behind with no address of its own is one the editor cannot see, and
            // one the next save would trip over as a duplicate.
            method_exists($entity, 'forceDeleteQuietly')
                ? $entity->forceDeleteQuietly()
                : $entity->deleteQuietly();

            throw $rejected;
        }
    }

    public function updated(Model $entity): void
    {
        try {
            $moved = $this->sync->sync($entity);
        } catch (PathRejected $rejected) {
            // The update is already in the table. Leaving it there would mean an entity whose
            // slug says one thing and whose address says another, and the next save would make
            // that permanent. The raw originals still hold what was there before this save —
            // `syncOriginal()` runs after this event, not before it — and they go back column
            // by column rather than through `fill()`, which would hand a translatable column
            // its own JSON as if it were a value in the current language.
            $original = $entity->getRawOriginal();

            // Only what is a column goes back. A model read with `withCount()` or a subselect
            // carries its aggregates among the raw attributes, and writing `children_count` back
            // into the table turned a clean "address taken" into an unknown-column error.
            $columns = $entity->getConnection()->getSchemaBuilder()->getColumnListing($entity->getTable());

            $entity->setRawAttributes($original, true);
            $entity->newQueryWithoutScopes()->whereKey($entity->getKey())->toBase()
                ->update(array_intersect_key($original, array_flip($columns)));

            throw $rejected;
        }

        if (! $moved) {
            return;
        }

        // The address changed, so every address below it in the tree changed with it. Each
        // descendant leaves its own alias behind, which is what keeps a moved branch from
        // taking a few thousand external links with it.
        $this->syncDescendants($entity);
    }

    /**
     * A node that changed place in the tree.
     *
     * `webx-ui/nested-set` rewrites bounds with bulk updates, so a move is not an `updated` —
     * without this event a page dragged under another one would keep its old address, and only
     * the next save of it would notice.
     */
    public function moved(Model $entity): void
    {
        $this->updated($entity);
    }

    /**
     * Into the bin, or gone for good.
     *
     * Either way the entity stops answering, aliases included. The difference is the trail: a
     * soft delete can be undone, and an undo that left every old link broken would undo only
     * half of it — so the former addresses are kept aside for {@see restored()}.
     */
    public function deleted(Model $entity): void
    {
        if (method_exists($entity, 'isForceDeleting') && ! $entity->isForceDeleting()) {
            $this->sync->trash($entity);

            return;
        }

        $this->sync->forget($entity);
    }

    public function restored(Model $entity): void
    {
        $this->sync->revive($entity);
    }

    private function syncDescendants(Model $entity): void
    {
        if (! method_exists($entity, 'descendants')) {
            return;
        }

        // Reloaded from the database rather than taken from memory: a move has just rewritten
        // the bounds of half the tree, and a node still holding the old ones would compute its
        // path from the place it used to be.
        foreach ($entity->descendants()->get() as $descendant) {
            $this->sync->sync($descendant);
        }
    }
}
