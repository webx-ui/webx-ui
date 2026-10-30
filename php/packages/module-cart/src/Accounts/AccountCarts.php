<?php

declare(strict_types=1);

namespace WebxUi\Cart\Accounts;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Cookie\CookieJar;
use Illuminate\Database\ConnectionInterface;
use WebxUi\Admin\Visitors\Events\VisitorDeleting;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Admin\Visitors\Events\VisitorSignedOut;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\CartRef;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Events\CartsMerging;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Resolver;

/**
 * The cart and the visitor's account (§13.3 of the visitors spec), through the events of the
 * panel's seam — never the visitors module itself, which the cart does not require.
 *
 * Sign-in: the guest's cart of the cookie joins the account's. A line like one already there adds
 * its qty up to the limit (decision 18), a bundle comes over whole and never merges; the guest's
 * cart goes — `CartsMerging` first, for what neighbours keep by it — and the cookie switches to the
 * account's. No account cart yet — the guest's becomes it.
 * Sign-out: the cookie goes, the account's cart waits for the next sign-in. Deleting the account:
 * its cart goes with it.
 */
final class AccountCarts
{
    public function __construct(
        private readonly CartCookie $cookie,
        private readonly CartHandle $handle,
        private readonly Resolver $resolver,
        private readonly CookieJar $cookies,
        private readonly ConnectionInterface $db,
        private readonly Repository $config,
        private readonly Dispatcher $events,
    ) {}

    public function signedIn(VisitorSignedIn $event): void
    {
        $this->handle->reset();
        $guest = $this->cookie->find($this->cookie->token());

        if ($guest === null) {
            return;
        }

        $account = Cart::query()->where('user_id', $event->visitorId)->first();
        $token = CartCookie::generate();

        $this->db->transaction(function () use ($guest, $account, $event, $token): void {
            $cart = $account ?? $guest;

            if ($account !== null) {
                $this->merge($guest, $account);
            }

            // A new token: whoever saw the guest's cookie does not hold the account's cart.
            $cart->user_id = $event->visitorId;
            $cart->token = CartCookie::hash($token);
            $cart->save();
        });

        $this->cookies->queue($this->cookie->make($token));
    }

    public function signedOut(VisitorSignedOut $event): void
    {
        $this->handle->reset();
        $this->cookies->queue($this->cookie->forget());
    }

    public function deleting(VisitorDeleting $event): void
    {
        $this->handle->reset();
        // The lines go by the foreign key's cascade, as when the visitor clears the cart.
        Cart::query()->where('user_id', $event->visitorId)->delete();
    }

    /** The guest's lines into the account's cart; the guest's cart is deleted. */
    private function merge(Cart $guest, Cart $account): void
    {
        /** @var list<CartItem> $existing */
        $existing = $account->items()->get()->all();
        /** @var list<CartItem> $incoming */
        $incoming = $guest->items()->get()->all();

        $targets = [];

        foreach (self::singles($existing) as $item) {
            $targets[self::signature($item)] ??= $item;
        }

        $merging = [];

        foreach (self::singles($incoming) as $item) {
            if (isset($targets[self::signature($item)])) {
                $merging[] = $item;
            }
        }

        $limits = $this->limits($merging, $guest->locale);
        $max = $this->qtyMax();

        foreach ($merging as $item) {
            $target = $targets[self::signature($item)];
            $target->qty = min($target->qty + $item->qty, $limits[(string) $item->id] ?? $max);
            $target->save();
            $item->delete();
        }

        // What did not merge comes over as it is: a bundle keeps its children, whose `parent_id`
        // is the same row in either cart.
        CartItem::query()->where('cart_id', $guest->id)->update(['cart_id' => $account->id]);

        $account->declined_rules = array_values(array_unique([...$account->declinedRules(), ...$guest->declinedRules()]));
        $account->locale = $guest->locale;
        // Before the row goes: a neighbour moves what it keeps by the guest's cart, or lets the
        // cascade take it.
        $this->events->dispatch(new CartsMerging($guest->id, $account->id));
        $guest->delete();
    }

    /**
     * The most of each merging line: the cart's `qty_max`, and the provider's `maxQty` when it
     * gives one. A line its provider no longer knows keeps the cart's maximum; the count warns.
     *
     * @param  list<CartItem>  $items
     * @return array<string, int> the guest line's id → its limit
     */
    private function limits(array $items, string $locale): array
    {
        $max = $this->qtyMax();
        $byType = [];

        foreach ($items as $item) {
            $byType[$item->type][] = new CartRef((string) $item->id, $item->ref_id, $item->options ?? []);
        }

        $limits = [];

        foreach ($byType as $type => $refs) {
            foreach ($this->resolver->resolve($type, $refs, $locale) as $key => $line) {
                $limits[$key] = $line?->maxQty !== null ? min($max, $line->maxQty) : $max;
            }
        }

        return $limits;
    }

    private function qtyMax(): int
    {
        $max = $this->config->get('webx-cart.qty_max');

        return is_numeric($max) ? (int) $max : 999;
    }

    /**
     * The lines that may merge: outside a bundle, and no bundle's parent.
     *
     * @param  list<CartItem>  $items
     * @return list<CartItem>
     */
    private static function singles(array $items): array
    {
        $parents = array_filter(array_map(static fn (CartItem $item): ?int => $item->parent_id, $items));

        return array_values(array_filter(
            $items,
            static fn (CartItem $item): bool => $item->parent_id === null && ! in_array($item->id, $parents, true),
        ));
    }

    private static function signature(CartItem $item): string
    {
        return $item->type.'#'.$item->ref_id.'#'.json_encode(CartService::normalise($item->options ?? []));
    }
}
