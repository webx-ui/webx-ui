<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use WebxUi\Cart\Events\CartsMerging;
use WebxUi\Orders\Checkout\Console\PruneDraftsCommand;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\SlotStep;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Checkout\Steps\CommentStep;
use WebxUi\Orders\Checkout\Steps\ContactsStep;
use WebxUi\Orders\Events\OrderPlacing;

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

    /** The positions of the checkout's own steps (§20.2); delivery 200 and payment 300 are slots (`CheckoutSlots::POSITIONS`). */
    public const int CONTACTS = 100;

    public const int COMMENT = 900;

    /** The limiters of the routes (§28): the confirmation, and a step, a minute an address. */
    public const string THROTTLE_PLACE = 'webx-orders-checkout';

    public const string THROTTLE_STEPS = 'webx-orders-checkout-steps';

    /**
     * The registries of the steps and the slots and the context of a request: bound on every site,
     * used only while it is on. The slots are asked for when the steps are: resolving the registry
     * runs the owners' `callAfterResolving(CheckoutSlots::class, …)` (§22.1).
     */
    public static function bind(Application $app): void
    {
        $app->singleton(CheckoutSlots::class);
        $app->singleton(CheckoutSteps::class, static function (Application $app): CheckoutSteps {
            $steps = new CheckoutSteps;
            $steps->register($app->make(ContactsStep::class), self::CONTACTS);
            $steps->register($app->make(CommentStep::class), self::COMMENT);

            foreach ($app->make(CheckoutSlots::class)->all() as $key => $slot) {
                $steps->register(new SlotStep($slot, $app->make(Views::class)), CheckoutSlots::POSITIONS[$key]);
            }

            return $steps;
        });
        // One count of the cart a request (§34).
        $app->scoped(CheckoutContexts::class);
        // The draft of the order the confirmation is placing, from the controller to the listener.
        $app->scoped(PlacingListener::class);
    }

    public static function register(Application $app): void
    {
        // The guest's cart goes when it joins the account's at sign-in (§21.3): its draft comes
        // over first, unless the account's cart has one of its own.
        $app->make('events')->listen(CartsMerging::class, [self::class, 'moveDraft']);
        // The agreement into the history and the draft cleared, in the order's transaction (§21.2).
        $app->make('events')->listen(OrderPlacing::class, PlacingListener::class);

        RateLimiter::for(self::THROTTLE_PLACE, static fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('webx-orders.checkout.throttle.place_per_minute', 10)),
        )->by(self::THROTTLE_PLACE.'|'.$request->ip()));
        RateLimiter::for(self::THROTTLE_STEPS, static fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('webx-orders.checkout.throttle.steps_per_minute', 60)),
        )->by(self::THROTTLE_STEPS.'|'.$request->ip()));

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
