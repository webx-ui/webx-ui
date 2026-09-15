<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\Models\Route;

/**
 * Keeps the registry in step with the entities.
 *
 * Everything that writes an address goes through here: the observer for one entity at a time,
 * `bulk()` for an import, `webx:routes:rebuild` for a scheme change. They share the formatter
 * and this code, which is the only reason a rebuild cannot disagree with a save and quietly
 * move half the site.
 */
class RouteSync
{
    /** A collision is settled by one retry in practice; three is for a pathological import. */
    private const RETRIES = 3;

    public function __construct(
        private readonly RouteTypes $types,
        private readonly UniquePath $unique,
        private readonly Locales $locales,
    ) {}

    /**
     * Give this entity the addresses it should have now.
     *
     * Whatever it had before and no longer has becomes an alias pointing at the new canonical
     * row — at the row, not at its text, so a second rename does not build a chain of 301s.
     *
     * Answers whether an address actually moved, which is what tells the observer whether a
     * subtree has to follow.
     */
    public function sync(Model $entity): bool
    {
        $type = $this->types->forEntity($entity);
        $moved = false;

        // The slugs as they stand before anything is resolved. A retry starts from them rather
        // than from the suffix the losing attempt picked, or a lost race would turn `belt-2`
        // into `belt-2-2` instead of `belt-3`.
        $original = $this->slugs($entity, $this->localeCodes());

        $this->retrying(function () use ($entity, $type, $original, &$moved): void {
            foreach ($original as $locale => $slug) {
                EntitySlug::write($entity, $locale, $slug);
            }

            $paths = $this->paths($entity, $type);

            $this->persistSlug($entity);

            DB::transaction(function () use ($entity, $paths, &$moved): void {
                /** @var EloquentCollection<int, Route> $rows */
                $rows = Route::query()->forEntity($entity)->get();

                foreach ($paths as $locale => $path) {
                    $moved = $this->syncOne($entity, $locale, $path, $rows->where('locale', $locale)) || $moved;
                }
            });
        });

        return $moved;
    }

    /** The entity is gone, so its addresses stop answering — aliases included (§9). */
    public function forget(Model $entity): void
    {
        Route::query()->forEntity($entity)->delete();
    }

    /**
     * Addresses for many entities at once.
     *
     * An import of a catalogue does not go through the observer: one insert per row per
     * language is the difference between a feed that finishes and one that does not. Entities
     * that already have an address are handed to `sync()` one by one instead — they are the
     * rare case in an import, and they are the case that has to leave an alias behind.
     *
     * @param  iterable<int, Model>  $entities
     */
    public function bulk(iterable $entities, int $chunk = 500): void
    {
        $batch = [];

        foreach ($entities as $entity) {
            $batch[] = $entity;

            if (count($batch) >= $chunk) {
                $this->bulkChunk($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->bulkChunk($batch);
        }
    }

    /**
     * What this entity's addresses would be, without writing anything.
     *
     * Note that settling a collision writes a suffix into the entity's slug (§5) — that is the
     * point of it — so this is "would be" only as far as the registry is concerned.
     *
     * @return array<string, string> Locale code to path.
     */
    public function paths(Model $entity, ?RouteType $type = null): array
    {
        $type ??= $this->types->forEntity($entity);

        $formatter = $type->formatter();
        $locales = $this->localeCodes();
        $paths = [];

        // A slug that is not translatable is one value shared by every language, so a suffix
        // picked for the second language changes the address of the first. Recompute until the
        // slugs stop moving; two passes is the realistic worst case, the third is a stop.
        for ($pass = 0; $pass < self::RETRIES; $pass++) {
            $before = $this->slugs($entity, $locales);
            $paths = [];

            foreach ($locales as $locale) {
                $paths[$locale] = $this->unique->for($entity, $locale, $formatter, $type->onConflict);
            }

            if ($this->slugs($entity, $locales) === $before) {
                break;
            }
        }

        return $paths;
    }

    /**
     * @param  EloquentCollection<int, Route>|Collection<int, Route>  $rows
     * @return bool Whether the canonical address of this language moved.
     */
    private function syncOne(Model $entity, string $locale, string $path, iterable $rows): bool
    {
        $current = null;
        $onPath = null;

        foreach ($rows as $row) {
            if ($row->kind === Route::CANONICAL && $current === null) {
                $current = $row;
            }

            if ($row->path === $path) {
                $onPath = $row;
            }
        }

        if ($current !== null && $current->path === $path) {
            $this->retarget($entity, $locale, $current);

            return false;
        }

        $canonical = $onPath ?? Route::query()
            ->where('locale', $locale)
            ->where('path', $path)
            ->first();

        if ($canonical === null) {
            $canonical = new Route;
        } elseif ($canonical->kind === Route::CANONICAL && ! $this->belongsTo($canonical, $entity)) {
            // `UniquePath` should have caught this; reaching it means another writer won the
            // race between the check and here. The retry loop recomputes and comes back.
            throw PathRejected::taken($path, EntitySlug::attribute($entity));
        }

        // An alias standing here — this entity's own from an earlier name, or another entity's
        // leftover — gives way to the live address (§2, decision 12).
        $canonical->forceFill([
            'locale' => $locale,
            'path' => $path,
            'kind' => Route::CANONICAL,
            'target_id' => null,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
        ])->save();

        if ($current !== null) {
            $current->forceFill([
                'kind' => Route::ALIAS,
                'target_id' => $canonical->getKey(),
            ])->save();
        }

        $this->retarget($entity, $locale, $canonical);

        return true;
    }

    /** Every alias of this entity in this language points at the canonical row, never a chain. */
    private function retarget(Model $entity, string $locale, Route $canonical): void
    {
        Route::query()
            ->forEntity($entity)
            ->where('locale', $locale)
            ->whereKeyNot($canonical->getKey())
            ->update(['kind' => Route::ALIAS, 'target_id' => $canonical->getKey()]);
    }

    private function belongsTo(Route $route, Model $entity): bool
    {
        return $route->entity_type === $entity->getMorphClass()
            && $route->entity_id === $entity->getKey();
    }

    /** @param  list<Model>  $entities */
    private function bulkChunk(array $entities): void
    {
        $rows = [];
        $moved = [];

        $existing = $this->existingPaths($entities);

        foreach ($entities as $entity) {
            $key = $entity->getMorphClass().'|'.(string) $entity->getKey();

            foreach ($this->paths($entity) as $locale => $path) {
                if (isset($existing[$key]) && ($existing[$key][$locale] ?? $path) !== $path) {
                    $moved[$key] = $entity;

                    continue 2;
                }

                $rows[] = [
                    'locale' => $locale,
                    'path' => $path,
                    'kind' => Route::CANONICAL,
                    'target_id' => null,
                    'entity_type' => $entity->getMorphClass(),
                    'entity_id' => $entity->getKey(),
                ];
            }

            $this->persistSlug($entity);
        }

        if ($rows !== []) {
            Route::query()->upsert($rows, ['locale', 'path'], ['kind', 'target_id', 'entity_type', 'entity_id']);
        }

        foreach ($moved as $entity) {
            $this->sync($entity);
        }
    }

    /**
     * @param  list<Model>  $entities
     * @return array<string, array<string, string>>
     */
    private function existingPaths(array $entities): array
    {
        $keys = [];

        foreach ($entities as $entity) {
            $keys[$entity->getMorphClass()][] = $entity->getKey();
        }

        $found = [];

        foreach ($keys as $morph => $ids) {
            $rows = Route::query()
                ->where('entity_type', $morph)
                ->whereIn('entity_id', $ids)
                ->where('kind', Route::CANONICAL)
                ->get();

            foreach ($rows as $row) {
                $found[$morph.'|'.(string) $row->entity_id][$row->locale] = $row->path;
            }
        }

        return $found;
    }

    /**
     * A suffix picked while resolving belongs in the entity, not only in the registry — the
     * form has to show the address the site will serve.
     */
    private function persistSlug(Model $entity): void
    {
        if ($entity->isDirty(EntitySlug::attribute($entity))) {
            $entity->saveQuietly();
        }
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    private function slugs(Model $entity, array $locales): array
    {
        $slugs = [];

        foreach ($locales as $locale) {
            $slugs[$locale] = EntitySlug::read($entity, $locale);
        }

        return $slugs;
    }

    /** @return list<string> */
    private function localeCodes(): array
    {
        $codes = $this->locales->codes();

        return $codes === [] ? [$this->locales->defaultCode()] : $codes;
    }

    /**
     * The unique index is the real guarantee behind every check above: two imports running at
     * once both see a free address and one of them loses on insert. Losing means recomputing,
     * not failing.
     *
     * @param  callable(): void  $work
     */
    private function retrying(callable $work): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $work();

                return;
            } catch (QueryException $exception) {
                if ($attempt >= self::RETRIES || ! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }
            }
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'UNIQUE constraint failed')      // SQLite
            || str_contains($message, 'Duplicate entry')                // MySQL, MariaDB
            || str_contains($message, 'duplicate key value');           // PostgreSQL
    }
}
