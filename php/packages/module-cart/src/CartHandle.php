<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Localization\LocaleCatalogue;
use WebxUi\Localization\Locales;

/**
 * The current visitor's cart, as `cart()` gives it to a template.
 *
 * `count()` and `has()` are for the header and the buy button on every page, so they are cheap:
 * no cookie — no query; a cookie — one query of the lines' types, ids and units, with the qty of
 * a bundle's child multiplied by its parent's in SQL, so the header and the full count agree.
 * Providers are not asked. `state()` is the full count, once a request.
 *
 * A signed-in visitor's cart is found by `user_id`, a guest's by the cookie (§13.3 of the visitors
 * spec). What it remembers is keyed by the one or the other and dropped by every write; the
 * owner too at the end of a request: a long-lived worker serves the next visitor with a clean slate.
 */
final class CartHandle
{
    public const string LOCALE_HEADER = 'X-Webx-Locale';

    /** @var array<string, list<array{type: string, ref: int, units: int}>> */
    private array $lines = [];

    /** @var array<string, CartState> */
    private array $states = [];

    /** The owner asked once a request: false — not asked yet. */
    private int|false|null $owner = false;

    public function __construct(
        private readonly CartCookie $cookie,
        private readonly CartOwner $owners,
        private readonly Calculator $calculator,
        private readonly Locales $locales,
    ) {}

    public function count(): int
    {
        return array_sum(array_column($this->lines(), 'units'));
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }

    /** Whether the cart holds this record — the "in the cart" state of a buy button. */
    public function has(string $type, int $ref): bool
    {
        foreach ($this->lines() as $line) {
            if ($line['type'] === $type && $line['ref'] === $ref) {
                return true;
            }
        }

        return false;
    }

    /** The full count of the chain (§5.5), in the language asked, else the request's. */
    public function state(?string $locale = null): CartState
    {
        $locale ??= $this->locale();
        $memo = ($this->key() ?? '').'|'.$locale;

        if (isset($this->states[$memo])) {
            return $this->states[$memo];
        }

        $cart = $this->cart();

        if ($cart === null) {
            return $this->states[$memo] = $this->calculator->empty($locale);
        }

        $cart->touchIfStale();

        return $this->states[$memo] = $this->calculator->calculate($cart, $locale, Purpose::Cart);
    }

    /**
     * The visitor's cart, or null: the account's for a signed-in visitor; a guest's by the cookie —
     * none without one, or when its cart is gone.
     */
    public function cart(): ?Cart
    {
        $owner = $this->owner();

        return $owner !== null
            ? Cart::query()->where('user_id', $owner)->first()
            : $this->cookie->find($this->cookie->token());
    }

    /** The signed-in visitor's id, or null for a guest; asked once a request. */
    public function owner(): ?int
    {
        if ($this->owner === false) {
            $this->owner = $this->owners->id();
        }

        return $this->owner;
    }

    /**
     * The language of the request: what the widget says the page is in, when the site has it,
     * else the application's.
     */
    public function locale(): string
    {
        $asked = request()->header(self::LOCALE_HEADER);

        if (is_string($asked) && $asked !== '' && $this->locales->has(LocaleCatalogue::normalise($asked))) {
            return LocaleCatalogue::normalise($asked);
        }

        return $this->locales->current();
    }

    /** Drop what is remembered of the cart: after a write. */
    public function forget(): void
    {
        $this->lines = [];
        $this->states = [];
    }

    /** Drop the owner as well: at the end of a request, and when the visitor signs in or out. */
    public function reset(): void
    {
        $this->forget();
        $this->owner = false;
    }

    /** @return list<array{type: string, ref: int, units: int}> */
    private function lines(): array
    {
        $key = $this->key();

        if ($key === null) {
            return [];
        }

        if (isset($this->lines[$key])) {
            return $this->lines[$key];
        }

        $owner = $this->owner();
        $query = CartItem::query()
            ->from('cart_items as items')
            ->join('carts', 'carts.id', '=', 'items.cart_id')
            ->leftJoin('cart_items as parents', 'parents.id', '=', 'items.parent_id');

        if ($owner !== null) {
            $query->where('carts.user_id', $owner);
        } else {
            $query->where('carts.token', CartCookie::hash((string) $this->cookie->token()))->whereNull('carts.user_id');
        }

        return $this->lines[$key] = $query
            ->orderBy('items.id')
            ->selectRaw('items.type as type, items.ref_id as ref, items.qty * coalesce(parents.qty, 1) as units')
            ->toBase()
            ->get()
            ->map(static fn (object $row): array => [
                'type' => (string) $row->type,
                'ref' => (int) $row->ref,
                'units' => (int) $row->units,
            ])
            ->values()
            ->all();
    }

    /** What opens the cart: the owner's id, else the cookie's token; null — nothing does. */
    private function key(): ?string
    {
        $owner = $this->owner();

        if ($owner !== null) {
            return 'user:'.$owner;
        }

        $token = $this->cookie->token();

        return $token !== null ? 'token:'.$token : null;
    }
}
