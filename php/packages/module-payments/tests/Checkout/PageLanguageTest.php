<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Checkout;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Tests\Checkout\UsesFakeSlots;
use WebxUi\Orders\Tests\Checkout\VisitsCheckout;
use WebxUi\Payments\Http\StepController;
use WebxUi\Payments\Types\BankTransfer;

/**
 * The payment step's API answers in the language of the page that asked, not the application's:
 * its address has no language, so the script says it in `X-Webx-Locale`; without it — the site's
 * main language. A site whose application language is English, its own Russian (main) and Ukrainian.
 */
final class PageLanguageTest extends PaymentsCheckoutCase
{
    use UsesFakeSlots;
    use VisitsCheckout;

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('webx-localization.locales', [['code' => 'ru', 'default' => true], ['code' => 'uk']]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableBuiltIns();
        $this->installSlots(shipping: true, payment: false);
        $this->shipping->choose('carrier-a-branch', new ShippingLine(ShippingLine::FIXED, amount: 300));
    }

    #[Test]
    public function the_choice_and_the_step_drawn_again_are_in_the_language_of_the_page(): void
    {
        $answer = $this->visit($this->cart(), '/api/payments/selection', ['_method' => 'PUT', 'method' => BankTransfer::METHOD], json: true, headers: [
            'X-Webx-Locale' => 'uk',
            StepController::VIEW_HEADER => 'step',
        ])->assertOk();

        $this->assertSame('За реквізитами', $answer->json('summary'));
        $this->assertStringContainsString('За реквізитами', (string) $answer->json('html'));
        $this->assertStringNotContainsString('Bank transfer', (string) $answer->json('html'));
    }

    #[Test]
    public function without_the_header_it_is_the_sites_main_language_not_the_applications(): void
    {
        $this->visit($this->cart(), '/api/payments/selection', ['_method' => 'PUT', 'method' => BankTransfer::METHOD], json: true)
            ->assertOk()
            ->assertJsonPath('summary', 'По реквизитам');
    }
}
