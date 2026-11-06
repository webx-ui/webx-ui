<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Templates\Cdata;
use WebxUi\Templates\Variable;

/**
 * One value a product gives a template (§4.4 of the feeds spec). It is asked for a whole page of
 * products at once: a variable that goes to the database per product turns a build into an N+1.
 */
interface ProductVariable
{
    /** The name, the label, the example, the fields of a list; {@see HtmlVariable} for HTML. */
    public function describe(): Variable;

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, string|list<array<string, string>>|Cdata|null> product id → value
     */
    public function values(Collection $products, string $locale): array;
}
