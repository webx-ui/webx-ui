<?php

declare(strict_types=1);

namespace WebxUi\Admin\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Versions\HasDraft;

/**
 * A record that points at records of other modules, or of its own: a recipe at its services and at
 * the recipes like it (§3.4 of the recipes spec).
 *
 * A relation is named by a role — the `name` of the field that edits it — and points at one type
 * of target from {@see RelationTargets}. What the site reads is only what a module on it still
 * answers for: rows pointing at a module that was removed wait for it, and come back with it.
 *
 * On a record with {@see HasDraft} the choice goes into the draft with the text around it and
 * takes effect when the record is published — "linked the recipe to a service" shows on the site
 * when the recipe is published, not when it is saved. The draft keeps it under one key,
 * {@see Relations::DRAFT}, which publishing hands back here through {@see setRelationsAttribute()}.
 *
 *     class Recipe extends Model
 *     {
 *         use HasRelations;
 *
 *         public function relationKey(): string { return 'recipe'; }
 *     }
 *
 *     $recipe->related('services');            // the services, in the order they were chosen
 *     Relations::load($recipes, 'services');    // for a list, in one go
 *
 * @mixin Model
 */
trait HasRelations
{
    /**
     * Relations waiting to be written by the next save — what publishing a draft laid over the
     * record. A copy that is never saved (a preview) reads them instead of the rows.
     *
     * @var array<string, array{target: string, ids: list<int>}>
     */
    protected array $pendingRelations = [];

    /** @var array<string, Collection<int, Model>> */
    protected array $loadedRelated = [];

    /** The owner's key in the rows: `recipe`. The same name the address registry knows it by. */
    abstract public function relationKey(): string;

    public static function bootHasRelations(): void
    {
        static::saved(static function (Model $record): void {
            if (method_exists($record, 'writePendingRelations')) {
                $record->writePendingRelations();
            }
        });

        $forget = static function (Model $record): void {
            if (method_exists($record, 'relationKey')) {
                Relations::rows($record)->where('owner_type', $record->relationKey())->where('owner_id', $record->getKey())->delete();
            }
        };

        // Soft deletes keep the rows: a recipe back from the bin comes back with its services.
        in_array(SoftDeletes::class, class_uses_recursive(static::class), true)
            ? static::forceDeleted($forget)
            : static::deleted($forget);
    }

    /**
     * What one role points at, in the order the editor put it in — only what an installed module
     * answers for and what is not in the bin. With `$visible`, only what the site shows.
     *
     * @return Collection<int, Model>
     */
    public function related(string $role, bool $visible = false, ?string $locale = null): Collection
    {
        if (array_key_exists($role, $this->pendingRelations)) {
            $related = $this->resolvePending($this->pendingRelations[$role]);
        } else {
            if (! array_key_exists($role, $this->loadedRelated)) {
                Relations::load([$this], $role);
            }

            $related = $this->loadedRelated[$role] ?? new Collection;
        }

        if (! $visible) {
            return $related;
        }

        $targets = Relations::targets();

        return $related->filter(static function (Model $record) use ($targets, $locale): bool {
            $key = $targets->keyOf($record);
            $target = $key === null ? null : $targets->find($key);

            return $target !== null && $target->visible($record, $locale);
        })->values();
    }

    /**
     * The ids of one role as the site has them, rows of removed modules included — the value is
     * the editor's, and a module coming back finds it whole.
     *
     * @return list<int>
     */
    public function relatedIds(string $role): array
    {
        /** @var list<int> $ids */
        $ids = Relations::rows($this)
            ->where('owner_type', $this->relationKey())
            ->where('owner_id', $this->getKey())
            ->where('role', $role)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('target_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * The ids of one role as the editor last left them: the draft's when the draft has the role,
     * the site's otherwise. What a form opens with.
     *
     * @return list<int>
     */
    public function draftedRelatedIds(string $role): array
    {
        $drafted = $this->draftedRelations()[$role]['ids'] ?? null;

        return is_array($drafted) ? $drafted : $this->relatedIds($role);
    }

    /**
     * Point one role at exactly these records, in this order — now, not in a draft. The record
     * itself is dropped from a relation to its own kind: a recipe is not like itself.
     *
     * @param  list<int>  $ids
     */
    public function syncRelated(string $role, string $targetType, array $ids): void
    {
        $ids = $this->cleanRelatedIds($targetType, $ids);
        $owner = $this->relationKey();

        $this->getConnection()->transaction(function () use ($role, $targetType, $ids, $owner): void {
            Relations::rows($this)->where('owner_type', $owner)->where('owner_id', $this->getKey())->where('role', $role)->delete();

            $now = Carbon::now();
            $rows = [];

            foreach ($ids as $position => $id) {
                $rows[] = [
                    'owner_type' => $owner,
                    'owner_id' => $this->getKey(),
                    'role' => $role,
                    'target_type' => $targetType,
                    'target_id' => $id,
                    'position' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                Relations::rows($this)->insert($rows);
            }
        });

        unset($this->loadedRelated[$role]);
    }

    /**
     * Save what a form sent for its relation fields, by role: into the draft on a record that has
     * one, straight into the rows on a record that does not.
     *
     * A role put back the way the site has it leaves the draft, so a save that changes nothing
     * does not mark the record "changed".
     *
     * @param  array<string, array{target: string, ids: list<int>}>  $relations
     */
    public function saveRelations(array $relations): void
    {
        if (! $this->draftsRelations()) {
            foreach ($relations as $role => $relation) {
                $this->syncRelated($role, $relation['target'], $relation['ids']);
            }

            return;
        }

        $drafted = $this->draftedRelations();

        foreach ($relations as $role => $relation) {
            $ids = $this->cleanRelatedIds($relation['target'], $relation['ids']);

            if ($ids === $this->relatedIds($role)) {
                unset($drafted[$role]);

                continue;
            }

            $drafted[$role] = ['target' => $relation['target'], 'ids' => $ids];
        }

        // Straight into the column rather than through `saveDraft()`: that replaces the whole
        // draft and writes an autosave, and this is one key of a draft the module writes itself.
        $column = $this->relationsDraftColumn();
        $draft = $this->getAttribute($column);
        $draft = is_array($draft) ? $draft : [];

        if ($drafted === []) {
            unset($draft[Relations::DRAFT]);
        } else {
            $draft[Relations::DRAFT] = $drafted;
        }

        $this->setAttribute($column, $draft === [] ? null : $draft);
        $this->save();
    }

    /**
     * Where a published draft hands its relations back: publishing lays every drafted key over the
     * attributes, and this one is not a column. They are written by the save that follows.
     */
    public function setRelationsAttribute(mixed $value): void
    {
        foreach (is_array($value) ? $value : [] as $role => $relation) {
            if (! is_string($role) || ! is_array($relation) || ! is_string($relation['target'] ?? null)) {
                continue;
            }

            $ids = is_array($relation['ids'] ?? null) ? $relation['ids'] : [];

            $this->pendingRelations[$role] = [
                'target' => $relation['target'],
                'ids' => array_values(array_map(intval(...), array_filter($ids, is_numeric(...)))),
            ];
        }
    }

    /**
     * @internal Called by the `saved` listener.
     */
    public function writePendingRelations(): void
    {
        $pending = $this->pendingRelations;
        $this->pendingRelations = [];

        foreach ($pending as $role => $relation) {
            $this->syncRelated($role, $relation['target'], $relation['ids']);
        }
    }

    /**
     * @internal Filled by {@see Relations::load()}.
     *
     * @param  Collection<int, Model>  $related
     */
    public function setLoadedRelated(string $role, Collection $related): void
    {
        $this->loadedRelated[$role] = $related;
    }

    /**
     * Only the records related through a role to these targets — "the recipes of this service".
     *
     * @param  Builder<covariant Model>  $query
     * @param  int|list<int>  $ids
     * @return Builder<covariant Model>
     */
    public function scopeRelatedTo(Builder $query, string $role, string $targetType, int|array $ids): Builder
    {
        return $query->whereIn(
            $this->qualifyColumn($this->getKeyName()),
            Relations::rows($this)
                ->select('owner_id')
                ->where('owner_type', $this->relationKey())
                ->where('role', $role)
                ->where('target_type', $targetType)
                ->whereIn('target_id', (array) $ids),
        );
    }

    /**
     * @return array<string, array{target: string, ids: list<int>}>
     */
    private function draftedRelations(): array
    {
        if (! $this->draftsRelations()) {
            return [];
        }

        $draft = $this->getAttribute($this->relationsDraftColumn());
        $drafted = is_array($draft) ? $draft[Relations::DRAFT] ?? null : null;

        /** @var array<string, array{target: string, ids: list<int>}> $drafted */
        return is_array($drafted) ? $drafted : [];
    }

    /** Asked by name rather than through {@see HasDraft}: this trait does not require it. */
    private function relationsDraftColumn(): string
    {
        return method_exists($this, 'draftColumn') ? (string) $this->draftColumn() : 'draft';
    }

    private function draftsRelations(): bool
    {
        return in_array(HasDraft::class, class_uses_recursive($this), true);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function cleanRelatedIds(string $targetType, array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id < 1 || ($targetType === $this->relationKey() && $id === (int) $this->getKey())) {
                continue;
            }

            $clean[$id] = true;
        }

        return array_keys($clean);
    }

    /**
     * @param  array{target: string, ids: list<int>}  $relation
     * @return Collection<int, Model>
     */
    private function resolvePending(array $relation): Collection
    {
        $wanted = [$relation['target'] => array_fill_keys($relation['ids'], true)];
        $found = Relations::find($wanted)[$relation['target']] ?? [];

        $list = [];

        foreach ($relation['ids'] as $id) {
            if (isset($found[$id])) {
                $list[] = $found[$id];
            }
        }

        return new Collection($list);
    }
}
