<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What the buyer chose for a cart (§5): the locality, the method, and the point or the address.
 * One row a cart, deleted with it.
 *
 * @property int $cart_id
 * @property string $country_code
 * @property int|null $locality_id
 * @property int|null $method_id
 * @property int|null $point_id
 * @property array<string, string|null>|null $address
 * @property Carbon|null $updated_at
 */
class DeliverySelection extends Model
{
    public const CREATED_AT = null;

    protected $table = 'delivery_selections';

    protected $primaryKey = 'cart_id';

    public $incrementing = false;

    protected $fillable = ['cart_id', 'country_code', 'locality_id', 'method_id', 'point_id', 'address'];

    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'locality_id' => 'integer',
            'method_id' => 'integer',
            'point_id' => 'integer',
            'address' => 'array',
        ];
    }
}
