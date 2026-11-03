<?php

declare(strict_types=1);

namespace WebxUi\Payments\Models;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Payments\Exchange\AppendOnly;

/**
 * The query builder of exchange events: reads only (decision 31). A mass update, delete or
 * truncate through the model is refused just like one through a row.
 *
 * @extends Builder<PaymentGatewayEvent>
 */
final class ExchangeEventBuilder extends Builder
{
    public function update(array $values): never
    {
        throw new AppendOnly('Exchange events are never changed.');
    }

    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw new AppendOnly('Exchange events are never changed.');
    }

    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw new AppendOnly('Exchange events are never changed.');
    }

    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw new AppendOnly('Exchange events are never changed.');
    }

    public function delete(): never
    {
        throw new AppendOnly('Exchange events are never deleted through the model: webx:payments:exchange-prune removes those older than a year.');
    }

    public function forceDelete(): never
    {
        $this->delete();
    }

    public function truncate(): never
    {
        throw new AppendOnly('Exchange events are never truncated.');
    }

    /** @param  array<array-key, mixed>  $values */
    public function insert(array $values): never
    {
        throw new AppendOnly('Exchange events are written by the journal only.');
    }
}
