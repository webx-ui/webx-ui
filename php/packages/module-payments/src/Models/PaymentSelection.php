<?php

declare(strict_types=1);

namespace WebxUi\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The method a buyer chose for a cart (spec §5): it survives a reload and the next day, and goes
 * with the cart.
 *
 * @property int $id
 * @property int $cart_id
 * @property string $method_key
 * @property Carbon|null $updated_at
 */
final class PaymentSelection extends Model
{
    public const CREATED_AT = null;

    protected $table = 'payment_selections';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cart_id' => 'integer'];
    }
}
