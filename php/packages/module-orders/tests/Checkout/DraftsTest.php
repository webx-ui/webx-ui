<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Tests\PlaceTestCase;

/**
 * The checkout's draft (spec §21): one per cart and gone with it, pruned after
 * `webx-orders.checkout.draft_days` whatever its cart, and carried over to the account's cart when
 * the guest's goes at sign-in.
 */
final class DraftsTest extends PlaceTestCase
{
    private const int VISITOR = 7;

    /** @param  array<string, mixed>  $with */
    private function draft(Cart $cart, array $with = []): CheckoutDraft
    {
        return CheckoutDraft::query()->create([
            'cart_id' => $cart->id,
            'customer' => ['first_name' => 'Buyer', 'last_name' => null, 'phone' => '+800 1234 5678', 'email' => 'buyer@example.com'],
            'last_step' => 'contacts',
            ...$with,
        ]);
    }

    private function guestCart(): Cart
    {
        $result = $this->cart();
        $this->app['request']->cookies->set($this->app->make(CartCookie::class)->name(), (string) $result->token);

        return $result->cart;
    }

    private function accountCart(): Cart
    {
        return $this->app->make(CartService::class)->add(null, [['type' => 'test.product', 'ref' => 2]], 'en', self::VISITOR)->cart;
    }

    #[Test]
    public function the_table_has_the_columns_of_the_spec_and_the_index_the_prune_reads(): void
    {
        $this->assertSame(
            ['cart_id', 'customer', 'recipient', 'comment', 'steps', 'terms_accepted_at', 'terms_version', 'place_key', 'shown_payable', 'last_step', 'created_at', 'updated_at'],
            Schema::getColumnListing('order_checkout_drafts'),
        );

        $indexes = array_map(static fn (array $index): string => ($index['primary'] ? 'primary ' : '').implode(',', $index['columns']), Schema::getIndexes('order_checkout_drafts'));
        $this->assertContains('primary cart_id', $indexes);
        $this->assertContains('updated_at', $indexes);
    }

    #[Test]
    public function a_draft_goes_with_its_cart(): void
    {
        $cart = $this->cart()->cart;
        $this->draft($cart);

        $this->app->make(CartService::class)->clear($cart);

        $this->assertSame(0, CheckoutDraft::query()->count());
    }

    #[Test]
    public function the_prune_counts_on_a_dry_run_and_then_takes_the_old_drafts_alone(): void
    {
        $old = $this->cart()->cart;
        $fresh = $this->cart()->cart;
        $this->draft($fresh);
        Carbon::setTestNow(Carbon::now()->subDays(31));
        $this->draft($old);
        Carbon::setTestNow();

        $this->artisan('webx:orders:checkout-prune', ['--dry-run' => true])
            ->expectsOutputToContain('1 draft untouched for 30 days would be deleted.')
            ->assertSuccessful();
        $this->assertSame(2, CheckoutDraft::query()->count());

        $this->artisan('webx:orders:checkout-prune')->expectsOutputToContain('1 draft untouched for 30 days deleted.')->assertSuccessful();

        $this->assertSame([$fresh->id], CheckoutDraft::query()->pluck('cart_id')->all());
        // The contacts go, the goods stay: the cart is the cart module's to prune.
        $this->assertTrue(Cart::query()->whereKey($old->id)->exists());

        $this->artisan('webx:orders:checkout-prune', ['--days' => 0])->assertFailed();
    }

    #[Test]
    public function the_prune_is_on_the_schedule(): void
    {
        $commands = array_map(static fn ($event): string => (string) $event->command, $this->app->make(Schedule::class)->events());

        $this->assertNotEmpty(array_filter($commands, static fn (string $command): bool => str_contains($command, 'webx:orders:checkout-prune')));
    }

    #[Test]
    public function the_guests_draft_moves_to_the_account_cart_that_has_none(): void
    {
        $account = $this->accountCart();
        $guest = $this->guestCart();
        $this->draft($guest, ['comment' => 'Ring twice']);

        event(new VisitorSignedIn(self::VISITOR, 'password'));

        $this->assertFalse(Cart::query()->whereKey($guest->id)->exists());
        $draft = CheckoutDraft::query()->sole();
        $this->assertSame($account->id, $draft->cart_id);
        $this->assertSame('Ring twice', $draft->comment);
        $this->assertSame('buyer@example.com', $draft->customer['email'] ?? null);
    }

    #[Test]
    public function the_account_carts_own_draft_stays_and_the_guests_goes_with_its_cart(): void
    {
        $account = $this->accountCart();
        $this->draft($account, ['comment' => 'Mine']);
        $guest = $this->guestCart();
        $this->draft($guest, ['comment' => 'The guest\'s']);

        event(new VisitorSignedIn(self::VISITOR, 'password'));

        $draft = CheckoutDraft::query()->sole();
        $this->assertSame($account->id, $draft->cart_id);
        $this->assertSame('Mine', $draft->comment);
    }

    #[Test]
    public function without_an_account_cart_the_draft_stays_with_the_guests_cart_that_becomes_it(): void
    {
        $guest = $this->guestCart();
        $this->draft($guest);

        event(new VisitorSignedIn(self::VISITOR, 'password'));

        $this->assertSame(self::VISITOR, $guest->refresh()->user_id);
        $this->assertSame($guest->id, CheckoutDraft::query()->sole()->cart_id);
    }
}
