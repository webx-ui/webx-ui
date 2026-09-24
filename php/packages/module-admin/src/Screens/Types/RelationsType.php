<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use WebxUi\Admin\Relations\HasRelations;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\Withdraws;

/**
 * `wx-relations`: the records of another module this one points at, in the order they were chosen
 * (§3.5 of the recipes spec). The role of the relation is the node's `name`, the kind of record its
 * `props.target`.
 *
 * The value is a list of ids. Where it is written is not this type's business — a screen is saved
 * by the module's form, which hands the relations to {@see HasRelations} — so
 * what is here is the check and the cleaning: repeats and records that do not exist are dropped,
 * the way `wx-categories` drops a double click rather than refusing it.
 *
 * A target no installed module answers for takes the node off the screen ({@see Withdraws}):
 * "Services" on the form of a recipe on a site without services is a picker of nothing.
 */
final class RelationsType implements Withdraws
{
    public function __construct(private readonly RelationTargets $targets) {}

    /**
     * @param  array<string, mixed>  $node
     */
    public function withdrawn(array $node): bool
    {
        return ! $this->targets->has(self::target($node));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) use ($node): void {
            $max = $node['props']['max'] ?? null;

            if (is_int($max) && $max > 0 && count(self::ids($value)) > $max) {
                $fail((string) __('webx-admin::relations.field-full', ['max' => $max]));
            }
        }];
    }

    /**
     * The ids that name something, in the order they came, each once. The bin counts as
     * something: a service chosen and then deleted is still the editor's choice, marked.
     *
     * @param  array<string, mixed>  $node
     * @return list<int>
     */
    public function store(mixed $value, array $node): array
    {
        $ids = self::ids($value);
        $target = $this->targets->find(self::target($node));

        if ($ids === [] || $target === null) {
            return $ids;
        }

        $known = $target->query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($ids)
            ->pluck($target->query()->getModel()->getKeyName())
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return array_values(array_intersect($ids, $known));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<int>
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): array
    {
        return self::ids($stored);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public static function target(array $node): string
    {
        $target = $node['props']['target'] ?? null;

        return is_string($target) ? $target : '';
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $one) {
            if ((is_int($one) && $one > 0) || (is_string($one) && ctype_digit($one) && (int) $one > 0)) {
                $ids[(int) $one] = true;
            }
        }

        return array_keys($ids);
    }
}
