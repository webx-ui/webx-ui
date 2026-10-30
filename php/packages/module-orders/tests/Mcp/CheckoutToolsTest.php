<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\AddResult;
use WebxUi\Orders\Checkout\ContactMask;
use WebxUi\Orders\Checkout\Events\CheckoutPayableChanged;
use WebxUi\Orders\Checkout\Events\CheckoutPlaced;
use WebxUi\Orders\Checkout\Events\CheckoutStepSaved;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderSettings;
use WebxUi\Orders\Tests\Checkout\UsesFakeSlots;
use WebxUi\Orders\Tests\Checkout\VisitsCheckout;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The checkout for an agent (spec §30, §32): the trial confirmation answers what the page's
 * `place` answers, a draft's contacts come masked, the tab «Checkout» is its own permission and its
 * dry run keeps nothing, and the funnel counts what the page's events say.
 */
final class CheckoutToolsTest extends McpTestCase
{
    use UsesFakeSlots;
    use VisitsCheckout;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SettingsServiceProvider::class];
    }

    private function fill(AddResult $cart, string $first = 'Buyer', string $last = 'Aster'): void
    {
        $this->visit($cart, '/checkout/steps/contacts', [
            'customer' => ['first_name' => $first, 'last_name' => $last, 'phone' => '+800 1234 5678', 'email' => 'buyer@example.com'],
        ])->assertRedirect();
    }

    /** @return array<string, mixed> */
    private function preview(AddResult $cart): array
    {
        return $this->ask('orders_checkout_preview', ['cart_id' => $cart->cart->id]);
    }

    #[Test]
    public function the_preview_answers_what_the_confirmation_answers(): void
    {
        // A step lacks something: the errors by field of the page's 422.
        $empty = $this->cart();
        $preview = $this->preview($empty);
        $this->assertSame('invalid', $preview['outcome']);
        $place = $this->submit($empty, json: true)->assertStatus(422)->json('errors');
        $this->assertSame($place, $preview['errors']);
        $this->assertSame('contacts.first_name_required', $preview['steps'][0]['errors'][0]['code']);

        // Ready: the order, with the amount the order comes to.
        $ready = $this->cart();
        $this->fill($ready);
        $preview = $this->preview($ready);
        $this->assertSame('order', $preview['outcome']);
        $this->assertSame(4999, $preview['payable']);
        $this->assertSame(['contacts', 'comment'], array_column($preview['steps'], 'key'));
        $this->submit($ready)->assertRedirect();
        $this->assertSame($preview['payable'], Order::query()->sole()->payable);

        // A line that cannot be bought: the 409 of the page, its code and its words.
        $gone = $this->cart([['type' => 'test.product', 'ref' => 3]]);
        $this->fill($gone);
        $this->products->lines[3] = self::product('Part C', 700, available: false);
        $preview = $this->preview($gone);
        $this->assertSame(['conflict', 'checkout.cart_not_orderable'], [$preview['outcome'], $preview['code']]);
        $place = $this->submit($gone, json: true)->assertStatus(409);
        $this->assertSame([$place->json('code'), $place->json('message')], [$preview['code'], $preview['message']]);

        // The price went up after the page was shown: the 409 of the new amount.
        $this->products->lines[3] = self::product('Part C', 700);
        $dearer = $this->cart([['type' => 'test.product', 'ref' => 2]]);
        $this->fill($dearer);
        $fields = $this->hidden($dearer);
        $this->products->lines[2] = self::product('Set B', 16000);
        $preview = $this->preview($dearer);
        $this->assertSame(['conflict', 'checkout.payable_changed'], [$preview['outcome'], $preview['code']]);
        $this->travel(5)->seconds();
        $place = $this->visit($dearer, '/checkout/place', $fields, json: true)->assertStatus(409);
        $this->assertSame([$place->json('code'), $place->json('message')], [$preview['code'], $preview['message']]);

        // Nothing of it wrote anything: one order, the one placed.
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function the_steps_say_why_one_is_not_shown_for_a_cart(): void
    {
        $this->installSlots();
        $this->products->lines[4] = self::product('Gift D', 2500, shippable: false);
        $cart = $this->cart([['type' => 'test.product', 'ref' => 4]]);

        $steps = $this->ask('orders_checkout_steps', ['cart_id' => $cart->cart->id])['steps'];

        $this->assertSame(['contacts', 'delivery', 'payment', 'comment'], array_column($steps, 'key'));
        $this->assertSame([100, 200, 300, 900], array_column($steps, 'position'));
        $delivery = self::pick($steps, 'key', 'delivery');
        $this->assertSame(['slot', false, 'no shippable lines in the cart'], [$delivery['kind'], $delivery['shown'], $delivery['why_not']]);
        $this->assertTrue(self::pick($steps, 'key', 'payment')['shown']);
    }

    #[Test]
    public function a_draft_comes_masked(): void
    {
        $cart = $this->cart();
        $this->fill($cart, 'Buyer', 'Aster');
        $this->hidden($cart);

        $answer = $this->ask('orders_checkout_drafts_list');

        $this->assertSame(1, $answer['total']);
        $draft = $answer['drafts'][0];
        $this->assertSame(['name' => 'B*** A', 'phone' => '+800 *** **78', 'email' => 'b***@example.com'], $draft['customer']);
        $this->assertSame([$cart->cart->id, 'contacts', 4999], [$draft['cart_id'], $draft['last_step'], $draft['shown_payable']]);

        $json = (string) json_encode($answer);
        foreach (['Buyer', 'Aster', '1234', 'buyer@'] as $full) {
            $this->assertStringNotContainsString($full, $json);
        }
    }

    #[Test]
    public function the_masks_keep_little(): void
    {
        $this->assertSame('B***', ContactMask::name('buyer'));
        $this->assertNull(ContactMask::name(' '));
        $this->assertSame('+44 *** **90', ContactMask::phone('+44 (20) 7946 0990'));
        $this->assertSame('*** **90', ContactMask::phone('8 800 555 3590'));
        $this->assertSame('***', ContactMask::phone('112'));
        $this->assertSame('***', ContactMask::email('no-at'));
    }

    #[Test]
    public function the_tab_checkout_is_its_own_permission_and_its_dry_run_keeps_nothing(): void
    {
        $reader = $this->manager(['orders.view', 'orders.statuses']);
        $this->agent('orders_checkout_settings_get', [], $reader)->assertOk();
        $this->agent('orders_checkout_settings_set', ['values' => ['orders.checkout.comment' => 'off']], $reader)
            ->assertHasErrors(['[orders.checkout.settings]']);

        $keeper = $this->manager(['orders.checkout.settings']);
        $dry = $this->ask('orders_checkout_settings_set', ['values' => ['orders.checkout.comment' => 'off', 'orders.checkout.fields.phone' => 'optional'], 'dry_run' => true], $keeper);

        $this->assertTrue($dry['dry_run']);
        $this->assertEqualsCanonicalizing(['The step «Comment» disappears.', 'The field «phone» is optional.'], $dry['changes']);
        $this->assertArrayNotHasKey('orders.checkout.comment', $this->app->make(Settings::class)->raw());

        // An order needs a phone or an e-mail: both optional is refused, dry or not.
        $this->agent('orders_checkout_settings_set', ['values' => ['orders.checkout.fields.phone' => 'optional', 'orders.checkout.fields.email' => 'optional'], 'dry_run' => true], $keeper)
            ->assertHasErrors(['orders.checkout.fields.phone']);
        $this->agent('orders_checkout_settings_set', ['values' => ['orders.checkout.number' => 1]], $keeper)->assertHasErrors(['orders.checkout.number']);

        $saved = $this->ask('orders_checkout_settings_set', ['values' => ['orders.checkout.comment' => 'off']], $keeper);
        $this->assertSame('off', $saved['values']['orders.checkout.comment']['value']);
        $this->assertSame('off', $this->app->make(OrderSettings::class)->get('orders.checkout.comment'));
        $steps = $this->ask('orders_checkout_steps', ['cart_id' => $this->cart()->cart->id])['steps'];
        $this->assertSame([true, false], array_column($steps, 'shown'));
    }

    #[Test]
    public function the_funnel_counts_what_the_page_s_events_say(): void
    {
        $this->listenFor(CheckoutStepSaved::class);
        $this->listenFor(CheckoutPayableChanged::class);
        $this->listenFor(CheckoutPlaced::class);

        // One stops at the contacts.
        $this->fill($this->cart());

        // One sees the price go up, confirms the new amount and places the order.
        $dearer = $this->cart([['type' => 'test.product', 'ref' => 2]]);
        $this->fill($dearer);
        $fields = $this->hidden($dearer);
        $this->products->lines[2] = self::product('Set B', 16000);
        $this->travel(5)->seconds();
        $this->visit($dearer, '/checkout/place', $fields, json: true)->assertStatus(409);
        $this->visit($dearer, '/checkout/place', [...$fields, 'expected_payable' => '16000'], json: true)->assertOk();

        $this->assertSame([2, 1, 1], [$this->times(CheckoutStepSaved::class), $this->times(CheckoutPayableChanged::class), $this->times(CheckoutPlaced::class)]);
        $this->assertSame(1, CheckoutDraft::query()->findOrFail($dearer->cart->id)->payable_changes);

        // An old draft is out of the day and the week.
        $old = $this->cart();
        $this->fill($old);
        CheckoutDraft::query()->whereKey($old->cart->id)->toBase()->update(['updated_at' => now()->subDays(10)]);

        $funnel = $this->ask('orders_checkout_funnel');

        $this->assertEquals(['contacts' => 1, 'confirm' => 1], $funnel['week']['by_last_step']);
        $this->assertSame([2, 1, 1], [$funnel['week']['drafts'], $funnel['week']['placed'], $funnel['week']['payable_changes']]);
        $this->assertEquals(['contacts' => 2, 'confirm' => 1], $funnel['month']['by_last_step']);
    }

    #[Test]
    public function the_resource_reads_the_registries(): void
    {
        $this->installSlots(payment: false);

        $catalog = ($this->resource('orders://checkout')->handler)();

        $this->assertSame(['contacts', 'delivery', 'comment'], array_column($catalog['steps'], 'key'));
        $this->assertArrayHasKey('contacts.phone_or_email', self::pick($catalog['steps'], 'key', 'contacts')['error_codes']);
        $this->assertArrayHasKey('delivery.failed', self::pick($catalog['steps'], 'key', 'delivery')['error_codes']);
        $this->assertNull(self::pick($catalog['slots'], 'key', 'payment')['registered']);
        $this->assertSame(409, $catalog['answers']['checkout.payable_changed']['status']);
        $this->assertSame('notice', $catalog['settings']['values']['orders.checkout.terms']['value']);
        $this->assertArrayHasKey(CheckoutPlaced::class, $catalog['events']);
    }
}
