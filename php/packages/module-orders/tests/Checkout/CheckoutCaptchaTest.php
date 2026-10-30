<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Orders\Checkout\CheckoutAntispam;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderSettings;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The site's captcha on the checkout (spec §28, §28.1): `orders.checkout.captcha` turns it on for
 * the confirmation and for «Buy in one click», the provider and the keys are the site's — on a
 * site without the inbox — and `on` without a provider is refused by the settings.
 */
final class CheckoutCaptchaTest extends CheckoutTestCase
{
    private const string VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SettingsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('webx-widgets.captcha.provider', 'turnstile');
        config()->set('webx-widgets.captcha.turnstile.key', 'site-key');
        config()->set('webx-widgets.captcha.turnstile.secret', 'site-secret');
    }

    #[Test]
    public function off_by_default_the_confirmation_draws_no_captcha_and_needs_none(): void
    {
        $cart = $this->cart();
        $this->fill($cart);

        $this->visit($cart, '/checkout/summary')->assertDontSee('data-webx-captcha', false);
        $this->submit($cart, json: true)->assertOk();

        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function on_the_confirmation_refuses_without_an_answer_and_places_with_a_good_one(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.captcha' => 'on']);
        $cart = $this->cart();
        $this->fill($cart);

        $this->visit($cart, '/checkout/summary')
            ->assertSee('data-webx-captcha="turnstile"', false)
            ->assertSee('data-webx-captcha-action="'.CheckoutAntispam::PLACE_ACTION.'"', false)
            ->assertSee('data-sitekey="site-key"', false);

        $this->submit($cart, json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors.captcha.0', 'Please confirm you are not a robot and send the form again.');
        $this->assertSame(0, Order::query()->count());

        Http::fake([self::VERIFY => Http::response(['success' => true])]);

        $this->submit($cart, ['cf-turnstile-response' => 'the-token'], json: true)->assertOk();
        $this->assertSame(1, Order::query()->count());
        Http::assertSent(static fn (ClientRequest $request): bool => $request['secret'] === 'site-secret' && $request['response'] === 'the-token');
    }

    #[Test]
    public function a_wrong_answer_is_no_order(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.captcha' => 'on']);
        Http::fake([self::VERIFY => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
        $cart = $this->cart();
        $this->fill($cart);

        $this->submit($cart, ['cf-turnstile-response' => 'forged'], json: true)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['captcha']);

        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function one_click_asks_the_same_captcha(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.quick.enabled' => 'on', 'orders.checkout.captcha' => 'on']);

        $html = Blade::render('<x-webx-orders::checkout.quick-button type="test.product" :ref="1" />');
        $this->assertStringContainsString('data-webx-captcha="turnstile"', $html);
        $this->assertStringContainsString('data-webx-captcha-action="'.CheckoutAntispam::QUICK_ACTION.'"', $html);

        $this->quick([])
            ->assertStatus(422)
            ->assertJsonPath('errors.captcha.0', 'Please confirm you are not a robot and send the form again.');
        $this->assertSame(0, Order::query()->count());

        Http::fake([self::VERIFY => Http::response(['success' => true])]);

        $this->quick(['cf-turnstile-response' => 'the-token'])->assertOk();
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function one_click_without_the_setting_needs_no_captcha(): void
    {
        $this->app->make(Settings::class)->save(['orders.checkout.quick.enabled' => 'on']);

        $this->assertStringNotContainsString('data-webx-captcha', Blade::render('<x-webx-orders::checkout.quick-button type="test.product" :ref="1" />'));
        $this->quick([])->assertOk();
    }

    #[Test]
    public function on_without_the_sites_captcha_is_refused_by_the_settings(): void
    {
        config()->set('webx-widgets.captcha.provider', null);

        try {
            $this->app->make(OrderSettings::class)->check(['orders.checkout.captcha' => 'on']);
            self::fail('On without a provider was kept.');
        } catch (ValidationException $e) {
            $this->assertSame(['Choose the site’s captcha first: Settings → Captcha.'], $e->errors()['orders.checkout.captcha']);
        }

        // Off is always fine, and on is once the site has one.
        $this->app->make(OrderSettings::class)->check(['orders.checkout.captcha' => 'off']);
        config()->set('webx-widgets.captcha.provider', 'turnstile');
        $this->app->make(OrderSettings::class)->check(['orders.checkout.captcha' => 'on']);
        $this->addToAssertionCount(2);
    }

    /**
     * @param  array<string, mixed>  $post
     * @return TestResponse<Response>
     */
    private function quick(array $post): TestResponse
    {
        $fields = [
            'type' => 'test.product',
            'ref' => '1',
            'qty' => '1',
            'phone' => '+800 1234 0042',
            CheckoutAntispam::HONEYPOT => '',
            CheckoutAntispam::TIMESTAMP => $this->app->make(CheckoutAntispam::class)->timestamp(),
            ...$post,
        ];
        $this->travel(5)->seconds();
        $this->flushHeaders();

        return $this->withHeaders(['Accept' => 'application/json'])->post('/checkout/quick', $fields);
    }
}
