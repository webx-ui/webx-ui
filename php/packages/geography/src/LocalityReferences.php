<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Geography\Models\GeoLocality;

/**
 * Who else holds a locality: points, carrier bindings, buyers' selections, orders. The library
 * knows none of them; each owner registers here from its provider, and the editor deletes a
 * locality only when nobody answers (decision 42) — otherwise it switches it off.
 */
final class LocalityReferences
{
    /** @var array<string, Closure(int): bool> */
    private array $checks = [];

    /**
     * @param  Closure(int): bool  $check  Whether the locality with this id is held.
     */
    public function register(string $name, Closure $check): void
    {
        $this->checks[$name] = $check;
    }

    /** The common case: a column of a table holds the locality's id. */
    public function table(string $table, string $column = 'locality_id', ?string $name = null): void
    {
        $this->register($name ?? $table, static fn (int $id): bool => Schema::hasTable($table)
            && DB::table($table)->where($column, $id)->exists());
    }

    /**
     * The names of whatever holds the locality, in the order they registered.
     *
     * @return list<string>
     */
    public function holding(GeoLocality $locality): array
    {
        $holding = [];

        foreach ($this->checks as $name => $check) {
            if ($check($locality->id)) {
                $holding[] = $name;
            }
        }

        return $holding;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->checks);
    }
}
