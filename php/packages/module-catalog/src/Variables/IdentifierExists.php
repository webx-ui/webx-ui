<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

use Illuminate\Support\Collection;
use WebxUi\Templates\Variable;

/**
 * `identifier_exists`: `no` when a product has neither a GTIN nor an MPN. A feed that takes `mpn`
 * from a property builds this again over its own `mpn`, or the two would disagree.
 */
final readonly class IdentifierExists implements ProductVariable
{
    public function __construct(private ProductVariable $mpn) {}

    public function describe(): Variable
    {
        return new Variable('identifier_exists', 'webx-catalog::variables.identifier_exists', 'yes');
    }

    public function values(Collection $products, string $locale): array
    {
        $mpn = $this->mpn->values($products, $locale);
        $values = [];

        foreach ($products as $product) {
            $id = (int) $product->id;
            $values[$id] = Gtin::main($product) !== null || ($mpn[$id] ?? '') !== '' ? 'yes' : 'no';
        }

        return $values;
    }
}
