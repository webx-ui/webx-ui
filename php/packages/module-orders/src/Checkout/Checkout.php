<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use WebxUi\Cart\Events\CartsMerging;
use WebxUi\Orders\Checkout\Console\PruneDraftsCommand;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;

/**
 * The checkout inside `module-orders` (spec §19–§34, decisions 75–80): the switch, and what it
 * wires into the site when it is on. Off (`webx-orders.checkout.enabled = false`) — nothing of it
 * is registered: no routes, no drafts kept, no prune command, no tab «Checkout» in the settings
 * (§19). Orders, the panel and the buyer's page stand as they were, and an order still comes from
 * the site's code through `Orders::place()`.
 */
final class Checkout
{
    public static function enabled(): bool
    {
        return (bool) config('webx-orders.checkout.enabled', true);
    }

    public static function register(Application $app): void
    {
        // The guest's cart goes when it joins the account's at sign-in (§21.3): its draft comes
        // over first, unless the account's cart has one of its own.
        $app->make('events')->listen(CartsMerging::class, [self::class, 'moveDraft']);

        $app->afterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command(PruneDraftsCommand::NAME)->daily()->name('webx-orders:checkout-prune');
        });
    }

    /** Inside the cart's transaction, while the guest's row still stands. */
    public static function moveDraft(CartsMerging $event): void
    {
        if (CheckoutDraft::query()->whereKey($event->accountCartId)->exists()) {
            return;
        }

        CheckoutDraft::query()->whereKey($event->guestCartId)->update(['cart_id' => $event->accountCartId]);
    }
}
