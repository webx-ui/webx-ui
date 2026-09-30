<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

/**
 * Columns nobody knows in advance — one per property (§3 of the exchange spec). Asked every time
 * the columns are listed, so a property added a minute ago is a column now.
 */
interface ExchangeColumnProvider
{
    /**
     * @return iterable<ExchangeColumn>
     */
    public function columns(): iterable;
}
