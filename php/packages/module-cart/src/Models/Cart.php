<?php

declare(strict_types=1);

namespace WebxUi\Cart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A visitor's cart. Found by the sha256 of the cookie's token; the token itself is never stored.
 *
 * @property int $id
 * @property string $token
 * @property string $locale
 * @property string $currency
 * @property list<string>|null $declined_rules
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Cart extends Model
{
    protected $table = 'carts';

    protected $fillable = ['token', 'locale', 'currency', 'declined_rules'];

    /** @var list<string> */
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'declined_rules' => 'array',
        ];
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    /**
     * The activity mark for the prune: a read moves `updated_at` only when it is a day old, so an
     * active cart costs one write a day, not one a page.
     */
    public function touchIfStale(): void
    {
        if ($this->updated_at === null || $this->updated_at->lt(Carbon::now()->subDay())) {
            $this->touch();
        }
    }

    /** @return list<string> */
    public function declinedRules(): array
    {
        return array_values(array_filter($this->declined_rules ?? [], is_string(...)));
    }
}
