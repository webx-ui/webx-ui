<?php

declare(strict_types=1);

namespace WebxUi\NestedSet;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\NestedSet\Exceptions\NestedSetException;

/**
 * Modified preorder tree traversal ("nested set") for Eloquent models.
 *
 * Every node carries the bounds of its own subtree, so "everything below this node" is one
 * indexed range scan instead of a query per level. The price is that an insert or a move
 * rewrites the bounds of everything to its right, which is why all of them run in a transaction.
 *
 * @mixin Model
 */
trait HasNestedSet
{
    private const PLACE_ROOT = 'root';

    private const PLACE_APPEND = 'append';

    private const PLACE_PREPEND = 'prepend';

    private const PLACE_BEFORE = 'before';

    private const PLACE_AFTER = 'after';

    public function getLftName(): string
    {
        return 'lft';
    }

    public function getRgtName(): string
    {
        return 'rgt';
    }

    public function getParentIdName(): string
    {
        return 'parent_id';
    }

    public function getDepthName(): string
    {
        return 'depth';
    }

    /**
     * Columns that separate independent trees kept in one table, e.g. ['site_id'].
     *
     * @return list<string>
     */
    public function getNestedSetScopeAttributes(): array
    {
        return [];
    }

    public static function bootHasNestedSet(): void
    {
        static::creating(static function (self $node): void {
            if ($node->getAttribute($node->getLftName()) === null) {
                $node->fillRootBounds();
            }
        });

        static::deleting(static function (self $node): void {
            $node->guardAgainstSoftDelete();
            $node->deleteDescendants();
        });

        static::deleted(static function (self $node): void {
            $node->closeGap($node->getLft(), $node->getNodeWidth());
        });
    }

    public function getLft(): int
    {
        return (int) $this->getAttribute($this->getLftName());
    }

    public function getRgt(): int
    {
        return (int) $this->getAttribute($this->getRgtName());
    }

    public function getDepth(): int
    {
        return (int) $this->getAttribute($this->getDepthName());
    }

    /** Bounds the node occupies: 2 for a leaf, plus 2 for every descendant. */
    public function getNodeWidth(): int
    {
        return $this->getRgt() - $this->getLft() + 1;
    }

    public function isRoot(): bool
    {
        return $this->getAttribute($this->getParentIdName()) === null;
    }

    public function isLeaf(): bool
    {
        return $this->getNodeWidth() === 2;
    }

    public function isDescendantOf(self $other): bool
    {
        return $this->isInSameTreeAs($other)
            && $this->getLft() > $other->getLft()
            && $this->getRgt() < $other->getRgt();
    }

    public function isChildOf(self $other): bool
    {
        return $this->getAttribute($this->getParentIdName()) === $other->getKey();
    }

    /** @return BelongsTo<static, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, $this->getParentIdName());
    }

    /** @return HasMany<static, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, $this->getParentIdName())->orderBy($this->getLftName());
    }

    /** @return Builder<static> */
    public function ancestors(): Builder
    {
        return $this->newNestedSetQuery()
            ->where($this->getLftName(), '<', $this->getLft())
            ->where($this->getRgtName(), '>', $this->getRgt())
            ->orderBy($this->getLftName());
    }

    /** @return Builder<static> */
    public function descendants(): Builder
    {
        return $this->newNestedSetQuery()
            ->where($this->getLftName(), '>', $this->getLft())
            ->where($this->getRgtName(), '<', $this->getRgt())
            ->orderBy($this->getLftName());
    }

    /** @return Builder<static> */
    public function siblings(): Builder
    {
        return $this->newNestedSetQuery()
            ->where($this->getParentIdName(), $this->getAttribute($this->getParentIdName()))
            ->whereKeyNot($this->getKey())
            ->orderBy($this->getLftName());
    }

    /**
     * The ancestors of the node followed by the node itself, root first.
     *
     * @return EloquentCollection<int, static>
     */
    public function pathFromRoot(): EloquentCollection
    {
        $path = $this->ancestors()->get();
        $path->push($this);

        return $path;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy($this->getLftName());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull($this->getParentIdName());
    }

    /**
     * Query limited to the tree this node belongs to.
     *
     * @return Builder<static>
     */
    public function newNestedSetQuery(): Builder
    {
        $query = $this->newQuery();

        foreach ($this->getNestedSetScopeAttributes() as $attribute) {
            $query->where($attribute, $this->getAttribute($attribute));
        }

        return $query;
    }

    public function saveAsRoot(): bool
    {
        return $this->place(null, self::PLACE_ROOT);
    }

    public function appendTo(self $parent): bool
    {
        return $this->place($parent, self::PLACE_APPEND);
    }

    public function prependTo(self $parent): bool
    {
        return $this->place($parent, self::PLACE_PREPEND);
    }

    public function insertBefore(self $sibling): bool
    {
        return $this->place($sibling, self::PLACE_BEFORE);
    }

    public function insertAfter(self $sibling): bool
    {
        return $this->place($sibling, self::PLACE_AFTER);
    }

    /** Swap places with the previous sibling. False when the node is already first. */
    public function up(): bool
    {
        $previous = $this->siblings()
            ->where($this->getRgtName(), '<', $this->getLft())
            ->orderByDesc($this->getLftName())
            ->first();

        return $previous !== null && $this->insertBefore($previous);
    }

    /** Swap places with the next sibling. False when the node is already last. */
    public function down(): bool
    {
        $next = $this->siblings()
            ->where($this->getLftName(), '>', $this->getRgt())
            ->orderBy($this->getLftName())
            ->first();

        return $next !== null && $this->insertAfter($next);
    }

    /**
     * Nest a flat, lft-ordered list: the roots come back with their `children` relation filled in.
     *
     * @param  iterable<int, static>  $nodes
     * @return EloquentCollection<int, static>
     */
    public static function toTree(iterable $nodes): EloquentCollection
    {
        $prototype = static::query()->getModel();

        /** @var EloquentCollection<int, static> $roots */
        $roots = $prototype->newCollection();
        $flat = [];
        $byKey = [];

        foreach ($nodes as $node) {
            $node->setRelation('children', $node->newCollection());
            $byKey[$node->getKey()] = $node;
            $flat[] = $node;
        }

        foreach ($flat as $node) {
            $parentKey = $node->getAttribute($node->getParentIdName());
            $parent = $parentKey === null ? null : ($byKey[$parentKey] ?? null);

            if ($parent === null) {
                $roots->push($node);

                continue;
            }

            /** @var EloquentCollection<int, static> $children */
            $children = $parent->getRelation('children');
            $children->push($node);
        }

        return $roots;
    }

    /**
     * Rebuild lft/rgt/depth from parent_id, keeping the current order. Returns how many rows
     * had to be corrected, so a scheduled check can report "0" and stay quiet.
     *
     * @param  array<string, mixed>  $scope
     */
    public static function fixTree(array $scope = []): int
    {
        $prototype = static::query()->getModel();
        $query = $prototype->newQuery();

        foreach ($scope as $column => $value) {
            $query->where($column, $value);
        }

        $nodes = $query
            ->orderBy($prototype->getLftName())
            ->orderBy($prototype->getKeyName())
            ->get();

        $present = [];

        foreach ($nodes as $node) {
            $present[$node->getKey()] = true;
        }

        // Orphans — a parent_id nobody answers to — are rebuilt as roots rather than dropped.
        $childrenOf = [];

        foreach ($nodes as $node) {
            $parentKey = $node->getAttribute($prototype->getParentIdName());
            $bucket = $parentKey !== null && isset($present[$parentKey]) ? $parentKey : '';
            $childrenOf[$bucket][] = $node;
        }

        $counter = 1;
        $fixed = 0;

        $walk = static function (string|int $bucket, int $depth) use (
            &$walk,
            &$counter,
            &$fixed,
            $childrenOf,
            $prototype
        ): void {
            foreach ($childrenOf[$bucket] ?? [] as $node) {
                $lft = $counter++;
                $walk($node->getKey(), $depth + 1);
                $rgt = $counter++;

                $unchanged = (int) $node->getAttribute($prototype->getLftName()) === $lft
                    && (int) $node->getAttribute($prototype->getRgtName()) === $rgt
                    && (int) $node->getAttribute($prototype->getDepthName()) === $depth;

                if ($unchanged) {
                    continue;
                }

                $node->newQueryWithoutScopes()->whereKey($node->getKey())->toBase()->update([
                    $prototype->getLftName() => $lft,
                    $prototype->getRgtName() => $rgt,
                    $prototype->getDepthName() => $depth,
                ]);

                $fixed++;
            }
        };

        $walk('', 0);

        return $fixed;
    }

    /**
     * Structural problems, one readable line each. An empty array means a healthy tree.
     *
     * @param  array<string, mixed>  $scope
     * @return list<string>
     */
    public static function checkTreeIntegrity(array $scope = []): array
    {
        $prototype = static::query()->getModel();
        $query = $prototype->newQuery();

        foreach ($scope as $column => $value) {
            $query->where($column, $value);
        }

        $nodes = $query->orderBy($prototype->getLftName())->get();
        $problems = [];
        $boundOwner = [];
        $byKey = [];

        foreach ($nodes as $node) {
            $byKey[$node->getKey()] = $node;
        }

        foreach ($nodes as $node) {
            $key = $node->getKey();
            $lft = (int) $node->getAttribute($prototype->getLftName());
            $rgt = (int) $node->getAttribute($prototype->getRgtName());

            if ($lft >= $rgt) {
                $problems[] = "Node [{$key}] has lft {$lft} at or above rgt {$rgt}.";
            }

            foreach ([$lft, $rgt] as $bound) {
                if (isset($boundOwner[$bound])) {
                    $problems[] = "Bound {$bound} is claimed by node [{$boundOwner[$bound]}] and node [{$key}].";
                }

                $boundOwner[$bound] = $key;
            }

            $parentKey = $node->getAttribute($prototype->getParentIdName());

            if ($parentKey === null) {
                continue;
            }

            $parent = $byKey[$parentKey] ?? null;

            if ($parent === null) {
                $problems[] = "Node [{$key}] points at a missing parent [{$parentKey}].";

                continue;
            }

            $parentLft = (int) $parent->getAttribute($prototype->getLftName());
            $parentRgt = (int) $parent->getAttribute($prototype->getRgtName());

            if ($lft <= $parentLft || $rgt >= $parentRgt) {
                $problems[] = "Node [{$key}] sits outside the bounds of its parent [{$parentKey}].";
            }
        }

        $count = $nodes->count();
        $expected = $count * 2;
        $highest = $boundOwner === [] ? 0 : max(array_keys($boundOwner));

        if ($highest !== $expected) {
            $problems[] = "The highest bound is {$highest}, but {$count} nodes need it to be {$expected}.";
        }

        return $problems;
    }

    private function place(?self $target, string $mode): bool
    {
        if ($target !== null && ! $target->exists) {
            throw NestedSetException::targetNotSaved();
        }

        return (bool) $this->getConnection()->transaction(function () use ($target, $mode): bool {
            $target = $this->syncTarget($target);

            return $this->exists
                ? $this->moveNode($target, $mode)
                : $this->insertNode($target, $mode);
        });
    }

    /**
     * Re-read the target and write the fresh bounds back into the caller's instance.
     *
     * Every placement shifts the bounds of the nodes around it, so a variable held across two
     * calls — `$child->appendTo($parent)` twice — would otherwise compute the second position
     * from bounds that stopped being true after the first.
     */
    private function syncTarget(?self $target): ?self
    {
        if ($target === null) {
            return null;
        }

        $fresh = $target->fresh();

        if ($fresh === null) {
            throw NestedSetException::targetMissing();
        }

        $target->setRawAttributes($fresh->getAttributes(), true);

        return $target;
    }

    private function insertNode(?self $target, string $mode): bool
    {
        if ($target !== null) {
            $this->copyScopeFrom($target);
            $this->assertSameTreeAs($target);
        }

        [$position, $depth, $parentId] = $this->resolvePlacement($target, $mode);

        $this->openGap($position, 2);

        $this->setAttribute($this->getLftName(), $position);
        $this->setAttribute($this->getRgtName(), $position + 1);
        $this->setAttribute($this->getDepthName(), $depth);
        $this->setAttribute($this->getParentIdName(), $parentId);

        $saved = (bool) $this->save();

        $this->syncTarget($target);

        return $saved;
    }

    /**
     * Moving a subtree takes four passes: park it in negative bounds, close the hole it left,
     * open a hole at the destination, bring it back shifted. Anything shorter breaks the moment
     * source and destination overlap, because the destination moves while the source is lifted.
     */
    private function moveNode(?self $target, string $mode): bool
    {
        $this->refresh();

        $lftName = $this->getLftName();
        $rgtName = $this->getRgtName();

        $left = $this->getLft();
        $right = $this->getRgt();
        $width = $this->getNodeWidth();
        $depthBefore = $this->getDepth();

        if ($target !== null) {
            $this->assertSameTreeAs($target);

            if ($target->getKey() === $this->getKey()
                || ($target->getLft() >= $left && $target->getRgt() <= $right)) {
                throw NestedSetException::movingIntoOwnSubtree();
            }
        }

        $this->newStructuralQuery()
            ->whereBetween($lftName, [$left, $right])
            ->update([
                $lftName => $this->negatedColumn($lftName, 0),
                $rgtName => $this->negatedColumn($rgtName, 0),
            ]);

        $this->closeGap($left, $width);

        // Closing the hole moved everything to the right of it, the target included.
        $target = $this->syncTarget($target);

        [$position, $depth, $parentId] = $this->resolvePlacement($target, $mode);

        $this->openGap($position, $width);

        $shift = $position - $left;
        $depthShift = $depth - $depthBefore;

        $this->newStructuralQuery()
            ->where($lftName, '<', 0)
            ->update([
                $lftName => $this->negatedColumn($lftName, $shift),
                $rgtName => $this->negatedColumn($rgtName, $shift),
                $this->getDepthName() => $this->shiftedColumn($this->getDepthName(), $depthShift),
            ]);

        $this->newQueryWithoutScopes()
            ->whereKey($this->getKey())
            ->toBase()
            ->update([$this->getParentIdName() => $parentId]);

        $this->refresh();
        $this->syncTarget($target);

        return true;
    }

    /**
     * Where the node lands: the lft it takes, the depth it gets, and its new parent key.
     *
     * @return array{int, int, mixed}
     */
    private function resolvePlacement(?self $target, string $mode): array
    {
        if ($target === null) {
            return [$this->nextRootPosition(), 0, null];
        }

        return match ($mode) {
            self::PLACE_APPEND => [$target->getRgt(), $target->getDepth() + 1, $target->getKey()],
            self::PLACE_PREPEND => [$target->getLft() + 1, $target->getDepth() + 1, $target->getKey()],
            self::PLACE_BEFORE => [
                $target->getLft(),
                $target->getDepth(),
                $target->getAttribute($this->getParentIdName()),
            ],
            self::PLACE_AFTER => [
                $target->getRgt() + 1,
                $target->getDepth(),
                $target->getAttribute($this->getParentIdName()),
            ],
            default => [$this->nextRootPosition(), 0, null],
        };
    }

    private function nextRootPosition(): int
    {
        $highest = $this->newStructuralQuery()->max($this->getRgtName());

        return $highest === null ? 1 : ((int) $highest) + 1;
    }

    private function openGap(int $position, int $size): void
    {
        $this->shiftBoundsFrom($position, $size);
    }

    private function closeGap(int $position, int $size): void
    {
        $this->shiftBoundsFrom($position, -$size);
    }

    /**
     * Slide every bound at or after $position by $size. Parked rows sit in negative bounds, so
     * they are never at or after a real position and stay untouched.
     */
    private function shiftBoundsFrom(int $position, int $size): void
    {
        if ($size === 0) {
            return;
        }

        $lftName = $this->getLftName();
        $rgtName = $this->getRgtName();

        $this->newStructuralQuery()
            ->where($lftName, '>=', $position)
            ->update([$lftName => $this->shiftedColumn($lftName, $size)]);

        $this->newStructuralQuery()
            ->where($rgtName, '>=', $position)
            ->update([$rgtName => $this->shiftedColumn($rgtName, $size)]);
    }

    private function deleteDescendants(): void
    {
        $this->newStructuralQuery()
            ->where($this->getLftName(), '>', $this->getLft())
            ->where($this->getRgtName(), '<', $this->getRgt())
            ->delete();
    }

    private function fillRootBounds(): void
    {
        $position = $this->nextRootPosition();

        $this->setAttribute($this->getLftName(), $position);
        $this->setAttribute($this->getRgtName(), $position + 1);
        $this->setAttribute($this->getDepthName(), 0);
    }

    private function guardAgainstSoftDelete(): void
    {
        if (method_exists($this, 'isForceDeleting') && ! $this->isForceDeleting()) {
            throw NestedSetException::softDeleteUnsupported();
        }
    }

    private function isInSameTreeAs(self $other): bool
    {
        foreach ($this->getNestedSetScopeAttributes() as $attribute) {
            if ($this->getAttribute($attribute) !== $other->getAttribute($attribute)) {
                return false;
            }
        }

        return true;
    }

    private function assertSameTreeAs(self $other): void
    {
        foreach ($this->getNestedSetScopeAttributes() as $attribute) {
            if ($this->getAttribute($attribute) !== $other->getAttribute($attribute)) {
                throw NestedSetException::differentScope($attribute);
            }
        }
    }

    private function copyScopeFrom(self $target): void
    {
        foreach ($this->getNestedSetScopeAttributes() as $attribute) {
            if ($this->getAttribute($attribute) === null) {
                $this->setAttribute($attribute, $target->getAttribute($attribute));
            }
        }
    }

    /**
     * Bounds are rewritten through the raw query builder on purpose: an Eloquent mass update
     * would stamp updated_at on every row a shift touches.
     */
    private function newStructuralQuery(): QueryBuilder
    {
        $query = $this->newQueryWithoutScopes()->toBase();

        foreach ($this->getNestedSetScopeAttributes() as $attribute) {
            $query->where($attribute, $this->getAttribute($attribute));
        }

        return $query;
    }

    private function shiftedColumn(string $column, int $delta): Expression
    {
        $wrapped = $this->getConnection()->getQueryGrammar()->wrap($column);
        $sign = $delta < 0 ? '-' : '+';

        return $this->getConnection()->raw("{$wrapped} {$sign} ".abs($delta));
    }

    private function negatedColumn(string $column, int $delta): Expression
    {
        $wrapped = $this->getConnection()->getQueryGrammar()->wrap($column);
        $sign = $delta < 0 ? '-' : '+';

        return $this->getConnection()->raw("(0 - {$wrapped}) {$sign} ".abs($delta));
    }
}
