<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\BenefitKeys;
use WebxUi\Cart\Tests\Fakes\FakeSources;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\BenefitSources;
use WebxUi\Cart\Totals\Calculator;

/** The registry of benefit keys modules offer to cart rules (decision 29 of the delivery spec). */
final class BenefitKeysTest extends TestCase
{
    private function keys(): BenefitKeys
    {
        return $this->app->make(BenefitKeys::class);
    }

    #[Test]
    public function the_registry_is_one_for_the_application_and_empty_in_the_cart_alone(): void
    {
        $this->assertSame($this->keys(), $this->keys());
        $this->assertSame([], $this->keys()->keys());
        $this->assertSame([], $this->keys()->options('en'));
    }

    #[Test]
    public function a_registered_key_is_offered_with_its_label_in_the_language_asked_in_registration_order(): void
    {
        $this->keys()->register('shipping.paid_by_store', ['en' => 'The shop pays for delivery', 'pl' => 'Sklep płaci za dostawę']);
        $this->keys()->register('gift.wrap', ['en' => 'Gift wrapping']);

        $this->assertTrue($this->keys()->has('shipping.paid_by_store'));
        $this->assertFalse($this->keys()->has('shipping'));
        $this->assertSame(['shipping.paid_by_store', 'gift.wrap'], $this->keys()->keys());
        $this->assertSame([
            'shipping.paid_by_store' => 'Sklep płaci za dostawę',
            // Not in Polish: the site's default language, not the key.
            'gift.wrap' => 'Gift wrapping',
        ], $this->keys()->options('pl'));
    }

    #[Test]
    public function a_label_missing_in_the_language_and_the_default_falls_back_to_any_there_is(): void
    {
        $this->keys()->register('gift.card', ['pl' => 'Kartka']);

        $this->assertSame(['gift.card' => 'Kartka'], $this->keys()->options('en'));
    }

    #[Test]
    public function a_label_may_be_a_line_of_the_owners_dictionary(): void
    {
        $this->app['translator']->addLines(['benefits.wrap' => 'Gift wrapping'], 'en', 'webx-test');
        $this->app['translator']->addLines(['benefits.wrap' => 'Pakowanie'], 'pl', 'webx-test');
        $this->keys()->register('gift.wrap', 'webx-test::benefits.wrap');

        $this->assertSame(['gift.wrap' => 'Gift wrapping'], $this->keys()->options('en'));
        $this->assertSame(['gift.wrap' => 'Pakowanie'], $this->keys()->options('pl'));
    }

    #[Test]
    public function a_key_registered_twice_is_refused(): void
    {
        $this->keys()->register('shipping.paid_by_store', ['en' => 'The shop pays for delivery']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'shipping.paid_by_store' is registered already");

        $this->keys()->register('shipping.paid_by_store', ['en' => 'Free delivery']);
    }

    #[Test]
    public function a_key_out_of_the_pattern_or_without_a_label_is_refused(): void
    {
        foreach (['Shipping.Paid', 'shipping paid', 'shipping.', '.paid', 'shipping-paid', ''] as $key) {
            try {
                $this->keys()->register($key, ['en' => 'Label']);
                $this->fail("The key '{$key}' was taken.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('lower-case letters', $e->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a label');

        $this->keys()->register('gift.wrap', []);
    }

    #[Test]
    public function a_benefit_nobody_registered_still_reaches_the_state(): void
    {
        // The registry offers keys to the rules' panel; the chain does not ask it.
        $this->app->make(BenefitSources::class)->register(new FakeSources(static fn (): array => [
            new Benefit('custom.flag', 'Custom', 'rule#1'),
        ]));

        $state = $this->app->make(Calculator::class)->empty('en');

        $this->assertFalse($this->keys()->has('custom.flag'));
        $this->assertTrue($state->hasBenefit('custom.flag'));
    }
}
