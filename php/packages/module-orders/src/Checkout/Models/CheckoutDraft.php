<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Cart\Models\Cart;

/**
 * What the buyer typed on the way to an order (spec §21.1), one per cart and gone with it. Written
 * only by a `POST` of the checkout — showing the page never makes one — and kept after the order
 * with the contacts alone (§21.2), until the prune takes it (§21.3).
 *
 * @property int $cart_id
 * @property array{first_name?: string, last_name?: string|null, phone?: string|null, email?: string|null}|null $customer
 * @property array{first_name?: string, last_name?: string|null, phone?: string|null}|null $recipient
 * @property string|null $comment
 * @property array<string, mixed>|null $steps
 * @property Carbon|null $terms_accepted_at
 * @property string|null $terms_version
 * @property string|null $place_key
 * @property int|null $shown_payable
 * @property string|null $last_step
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CheckoutDraft extends Model
{
    protected $table = 'order_checkout_drafts';

    protected $primaryKey = 'cart_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'customer' => 'array',
            'recipient' => 'array',
            'steps' => 'array',
            'terms_accepted_at' => 'datetime',
            'shown_payable' => 'integer',
        ];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
