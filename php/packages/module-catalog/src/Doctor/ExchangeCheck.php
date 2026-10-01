<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Doctor;

use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Catalog\Exchange\ExchangeColumns;

/**
 * A column of the exchange that is not called what its owner thinks (§3 of the exchange spec).
 *
 * The code of a property is chosen in the panel by somebody who never heard of the core's columns,
 * and one called `price` becomes `p_price` in every file. Nothing breaks — which is the trouble: a
 * supplier's price list with a `price` column of that property fills the product's price instead,
 * and nobody sees why until the prices are wrong.
 */
final class ExchangeCheck implements Check
{
    public function __construct(private readonly ExchangeColumns $columns) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $found = [];

        foreach ($this->columns->renamed() as $code => $as) {
            $found[] = $as === null
                ? Diagnosis::warn('Catalogue exchange', "the column [{$code}] of a property is taken, and so is [p_{$code}]: files cannot name this property until its code is changed.")
                : Diagnosis::warn('Catalogue exchange', "[{$code}] is a column of the catalogue already, so the property of that code is [{$as}] in files — change the property's code if files should name it [{$code}].");
        }

        return $found;
    }
}
