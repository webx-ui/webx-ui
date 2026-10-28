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
 * What it remembers is keyed by the token and dropped by every write and at the end of a
 * request: a long-lived worker serves the next visitor with a clean slate.
 */
final class CartHandle
{
    public const string LOCALE_HEADER = 'X-Webx-Locale';

    /** @var array<string, list<array{type: string, ref: int, units: int}>> */
    private array $lines = [];

    /** @var array<string, CartState> */
    private array $states = [];

    public function __construct(
        private readonly CartCookie $cookie,
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
        $token = $this->cookie->token();
        $memo = ($token ?? '').'|'.$locale;

        if (isset($this->states[$memo])) {
            return $this->states[$memo];
        }

        $cart = $this->cookie->find($token);

        if ($cart === null) {
            return $this->states[$memo] = $this->calculator->empty($locale);
        }

        $cart->touchIfStale();

        return $this->states[$memo] = $this->calculator->calculate($cart, $locale, Purpose::Cart);
    }

    /** The visitor's cart, or null — no cookie, or a cookie whose cart is gone. */
    public function cart(): ?Cart
    {
        return $this->cookie->find($this->cookie->token());
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

    /** Drop what is remembered: after a write, and at the end of a request. */
    public function forget(): void
    {
        $this->lines = [];
        $this->states = [];
    }

    /** @return list<array{type: string, ref: int, units: int}> */
    private function lines(): array
    {
        $token = $this->cookie->token();

        if ($token === null) {
            return [];
        }

        return $this->lines[$token] ??= CartItem::query()
            ->from('cart_items as items')
            ->join('carts', 'carts.id', '=', 'items.cart_id')
            ->leftJoin('cart_items as parents', 'parents.id', '=', 'items.parent_id')
            ->where('carts.token', CartCookie::hash($token))
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
}
