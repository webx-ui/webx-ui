<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

use Closure;
use Illuminate\Support\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Templates\Variable;

/**
 * A variable made of a description and a function over the page: what most of them are, in the
 * core and in a satellite alike.
 */
final readonly class ComputedVariable implements ProductVariable
{
    /**
     * @param  Closure(Collection<int, Product>, string): array<int, mixed>  $values
     */
    public function __construct(private Variable $variable, private Closure $values) {}

    public function describe(): Variable
    {
        return $this->variable;
    }

    public function values(Collection $products, string $locale): array
    {
        return ($this->values)($products, $locale);
    }
}
