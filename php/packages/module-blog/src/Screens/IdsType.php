<?php

declare(strict_types=1);

namespace WebxUi\Blog\Screens;

use Closure;
use Illuminate\Database\ConnectionResolverInterface;
use WebxUi\Admin\Screens\FieldType;

/**
 * A field that holds a list of ids of one table, in the order the editor put them in.
 *
 * `wx-article-rubrics`, `wx-article-tags` and `wx-article-related` are all this: what differs
 * between them is the table behind the ids and the control drawn over them, and neither of
 * those is a reason for three classes.
 *
 * The order is the value. For rubrics it decides which one is the main one (§2.6), for related
 * articles it is the order they are shown in — so the list is never sorted here, only stripped
 * of what is not an id and of repeats. A repeat is dropped rather than refused: it is a double
 * click, not a mistake worth a red field.
 *
 * The rule is one query rather than `exists` per element, because `ScreenValues` checks a field
 * as one value and has no `*` to hang per-element rules off. It also has to be one query for a
 * list of thirty related articles to stay one query.
 */
final class IdsType implements FieldType
{
    /**
     * @param  string  $table  Where the ids have to exist.
     * @param  string  $message  What to say when one of them does not.
     */
    public function __construct(
        private readonly ConnectionResolverInterface $connection,
        private readonly string $table,
        private readonly string $message,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail): void {
            $ids = $this->ids($value);

            if ($ids === []) {
                return;
            }

            $found = $this->connection->connection()->table($this->table)->whereIn('id', $ids)->count();

            if ($found !== count($ids)) {
                $fail((string) __($this->message));
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<int>
     */
    public function store(mixed $value, array $node): array
    {
        return $this->ids($value);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<int>
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): array
    {
        return $this->ids($stored);
    }

    /**
     * Whatever arrived, as the list of ids it was meant to be.
     *
     * A value that is not a number is dropped rather than failed: the panel sends ids, and
     * anything else in the list is a request that did not come from a form.
     *
     * @return list<int>
     */
    private function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $one) {
            if (is_int($one) || (is_string($one) && ctype_digit($one))) {
                $ids[(int) $one] = (int) $one;
            }
        }

        return array_values($ids);
    }
}
