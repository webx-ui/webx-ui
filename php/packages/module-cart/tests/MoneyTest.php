<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Cart\ConfigCurrency;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\CurrencyFormat;
use WebxUi\Cart\Doctor\CurrencyCheck;
use WebxUi\Cart\Money;

final class MoneyTest extends TestCase
{
    private const string NBSP = "\u{00A0}";

    private function money(): Money
    {
        return $this->app->make(Money::class);
    }

    #[Test]
    public function common_currencies_are_written_by_their_own_rules(): void
    {
        $money = $this->money();

        $this->assertSame('49,99'.self::NBSP.'€', $money->format(4999, 'EUR'));
        $this->assertSame('1'.self::NBSP.'234'.self::NBSP.'567,89'.self::NBSP.'€', $money->format(123456789, 'EUR'));
        $this->assertSame('$1,234.56', $money->format(123456, 'USD'));
        $this->assertSame('¥1,235', $money->format(1235, 'JPY'));
        $this->assertSame('0,05'.self::NBSP.'€', $money->format(5, 'EUR'));
        $this->assertSame('-$0.99', $money->format(-99, 'USD'));
        $this->assertSame('0,00'.self::NBSP.'€', $money->format(0, 'eur'));
    }

    #[Test]
    public function an_unknown_currency_writes_its_code_after_the_number_with_the_iso_digits(): void
    {
        $money = $this->money();

        $this->assertSame('1'.self::NBSP.'234,56'.self::NBSP.'XYZ', $money->format(123456, 'XYZ'));
        $this->assertSame('1,234'.self::NBSP.'KWD', $money->format(1234, 'KWD'));
        $this->assertSame(3, $money->exponent('KWD'));
        $this->assertSame('1'.self::NBSP.'234'.self::NBSP.'CLP', $money->format(1234, 'CLP'));
    }

    #[Test]
    public function the_string_is_the_same_in_every_language_of_the_site(): void
    {
        $written = [];

        foreach (['en', 'pl', 'de', 'ja'] as $locale) {
            $this->app->setLocale($locale);
            $written[] = $this->money()->format(250000, 'EUR');
        }

        $this->assertSame(['2'.self::NBSP.'500,00'.self::NBSP.'€'], array_values(array_unique($written)));
    }

    #[Test]
    public function a_decimal_becomes_minor_units_without_a_float(): void
    {
        $money = $this->money();

        $this->assertSame(4999, $money->toMinor('49.99', 'EUR'));
        $this->assertSame(4990, $money->toMinor('49.9', 'EUR'));
        $this->assertSame(5000, $money->toMinor('50', 'EUR'));
        $this->assertSame(30, $money->toMinor('0.30', 'EUR'));
        $this->assertSame(1235, $money->toMinor('1234.50', 'JPY'));
        $this->assertSame(1234, $money->toMinor('1234.49', 'JPY'));
        $this->assertSame(-1001, $money->toMinor('-10.005', 'EUR'));
        $this->assertSame(1234567, $money->toMinor('1234.567', 'KWD'));
        $this->assertSame(700, $money->toMinor(7, 'USD'));

        $this->expectException(InvalidArgumentException::class);
        $money->toMinor('12,50', 'EUR');
    }

    #[Test]
    public function a_currency_source_of_its_own_changes_the_format(): void
    {
        $this->app->instance(CurrencySource::class, new class implements CurrencySource
        {
            public function currency(): string
            {
                return 'EUR';
            }

            public function format(string $currency): CurrencyFormat
            {
                return new CurrencyFormat('EUR', true, true, '.', ',', 2);
            }
        });
        $this->app->forgetInstance(Money::class);

        $this->assertSame('EUR'.self::NBSP.'1.234,56', $this->money()->format(123456, 'EUR'));
    }

    #[Test]
    public function the_currency_is_the_carts_then_the_catalogues_then_none(): void
    {
        $source = new ConfigCurrency($this->app['config']);

        config()->set('webx-cart.currency', 'eur');
        config()->set('webx-catalog.price.currency', 'USD');
        $this->assertSame('EUR', $source->currency());

        config()->set('webx-cart.currency', null);
        $this->assertSame('USD', $source->currency());

        config()->set('webx-catalog.price.currency', '');
        $this->assertNull($source->currency());
    }

    #[Test]
    public function doctor_names_the_setting_when_there_is_no_currency(): void
    {
        $this->assertContains(CurrencyCheck::class, $this->app->make(DoctorChecks::class)->all());

        $check = $this->app->make(CurrencyCheck::class);
        $this->assertSame('ok', $check->run()[0]->state);

        config()->set('webx-cart.currency', null);
        $found = $check->run();
        $this->assertSame('warn', $found[0]->state);
        $this->assertStringContainsString('WEBX_CART_CURRENCY', $found[0]->detail);
    }

    #[Test]
    public function a_currency_source_bound_first_wins_over_the_default(): void
    {
        $own = new class implements CurrencySource
        {
            public function currency(): string
            {
                return 'USD';
            }

            public function format(string $currency): CurrencyFormat
            {
                return new CurrencyFormat('$', true, false, ',', '.', 2);
            }
        };

        // As a currencies module whose provider ran before the cart's would.
        $app = $this->createApplication();
        $app->instance(CurrencySource::class, $own);
        (new CartServiceProvider($app))->register();

        $this->assertSame($own, $app->make(CurrencySource::class));
    }
}
