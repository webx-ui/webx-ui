<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The agreement with the terms is a line of the order's history (spec §23.4, decision 67): the
 * document, its address and version, when — in the order's language, in the order's transaction.
 */
final class CheckoutTermsTest extends CheckoutTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SettingsServiceProvider::class];
    }

    #[Test]
    public function the_notice_agreed_by_pressing_the_button_goes_into_the_history(): void
    {
        $this->app->make(Settings::class)->save([
            'orders.checkout.terms' => 'notice',
            'orders.checkout.terms_page' => ['target' => 'url', 'url' => 'https://shop.example.test/terms'],
            'orders.checkout.privacy_page' => ['target' => 'url', 'url' => 'https://shop.example.test/privacy'],
        ]);
        $this->travelTo('2026-10-29 12:00:00');
        $cart = $this->cart();
        $this->fill($cart);

        $this->submit($cart)->assertRedirect();

        $this->assertSame([
            'Agreement with the terms: Terms of sale (https://shop.example.test/terms), version unknown, 2026-10-29 12:00:05 UTC',
            'Agreement with the terms: Privacy policy (https://shop.example.test/privacy), version unknown, 2026-10-29 12:00:05 UTC',
        ], $this->notes(Order::query()->sole()));
    }

    #[Test]
    public function the_box_ticked_is_remembered_until_the_order_and_written_with_its_time(): void
    {
        $this->app->make(Settings::class)->save([
            'orders.checkout.terms' => 'checkbox',
            'orders.checkout.terms_page' => ['target' => 'url', 'url' => 'https://shop.example.test/terms'],
        ]);
        $this->travelTo('2026-10-29 12:00:00');
        $cart = $this->cart();
        $this->fill($cart);
        $fields = $this->hidden($cart);

        // Ticked, but the amount moved: no order, and the box stays ticked on the fresh column.
        $this->travel(5)->seconds();
        $this->visit($cart, '/checkout/place', [...$fields, 'expected_payable' => '1', 'terms' => '1'], json: true)
            ->assertStatus(409)
            ->assertJsonPath('html', fn (string $html): bool => (bool) preg_match('/name="terms"[^>]*checked/', $html));
        $draft = CheckoutDraft::query()->findOrFail($cart->cart->id);
        $this->assertSame('2026-10-29 12:00:05', $draft->terms_accepted_at?->format('Y-m-d H:i:s'));
        $this->assertSame('https://shop.example.test/terms', $draft->terms_version);

        // Another version of the terms: the box is to be ticked again.
        $this->app->make(Settings::class)->save(['orders.checkout.terms_page' => ['target' => 'url', 'url' => 'https://shop.example.test/terms-2']]);
        $this->assertDoesNotMatchRegularExpression('/name="terms"[^>]*checked/', (string) $this->visit($cart, '/checkout/summary')->getContent());
        $this->app->make(Settings::class)->save(['orders.checkout.terms_page' => ['target' => 'url', 'url' => 'https://shop.example.test/terms']]);

        $this->travel(60)->seconds();
        $this->visit($cart, '/checkout/place', [...$fields, 'terms' => '1'])->assertRedirect();

        $this->assertSame(
            ['Agreement with the terms: Terms of sale (https://shop.example.test/terms), version unknown, 2026-10-29 12:00:05 UTC'],
            $this->notes(Order::query()->sole()),
        );
        $this->assertNull(CheckoutDraft::query()->findOrFail($cart->cart->id)->terms_accepted_at);
    }

    #[Test]
    public function no_terms_no_line(): void
    {
        $this->app->make(Settings::class)->save([
            'orders.checkout.terms' => 'none',
            'orders.checkout.terms_page' => ['target' => 'url', 'url' => 'https://shop.example.test/terms'],
        ]);
        $cart = $this->cart();
        $this->fill($cart);

        $this->submit($cart)->assertRedirect();

        $this->assertSame([], $this->notes(Order::query()->sole()));
    }

    /** @return list<string> */
    private function notes(Order $order): array
    {
        return $order->history()->where('kind', StatusChange::NOTE)->orderBy('id')->pluck('note')->all();
    }
}
