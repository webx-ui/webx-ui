<?php

declare(strict_types=1);

namespace WebxUi\Cart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A line: what a provider of `type` knows as `ref_id`, `qty` of it. A child of a bundle counts its
 * `qty` per unit of the parent, one level deep. The name and the price are snapshots for when the
 * record is gone and for "it got cheaper" — what is shown and summed is always the provider's now.
 *
 * @property int $id
 * @property int $cart_id
 * @property string $type
 * @property int $ref_id
 * @property array<string, mixed>|null $options
 * @property int $qty
 * @property int|null $parent_id
 * @property array<string, string> $name_at_add
 * @property int $price_at_add
 * @property Carbon $added_at
 */
class CartItem extends Model
{
    protected $table = 'cart_items';

    public $timestamps = false;

    protected $fillable = ['cart_id', 'type', 'ref_id', 'options', 'qty', 'parent_id', 'name_at_add', 'price_at_add', 'added_at'];

    protected function casts(): array
    {
        return [
            'ref_id' => 'integer',
            'qty' => 'integer',
            'parent_id' => 'integer',
            'options' => 'array',
            'name_at_add' => 'array',
            'price_at_add' => 'integer',
            'added_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return HasMany<CartItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }
}
