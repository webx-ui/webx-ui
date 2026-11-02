<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A locality the shop's courier goes to (§5). A pivot of its own so that a row written one by one
 * moves the cache group `delivery` like any model; `syncLocalities()` moves it for a whole list.
 *
 * @property int $method_id
 * @property int $locality_id
 */
class DeliveryMethodLocality extends Pivot
{
    protected $table = 'delivery_method_localities';

    public $incrementing = false;

    public $timestamps = false;
}
