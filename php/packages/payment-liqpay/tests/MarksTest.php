<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payments\PaymentMarks;

/** The switches of a method and the marks they show (groups spec §3.5). */
final class MarksTest extends TestCase
{
    use Recorded;

    private const array ALL_OFF = [
        'pay_card' => false,
        'pay_apay' => false,
        'pay_gpay' => false,
        'pay_privat24' => false,
        'pay_qr' => false,
        'pay_moment_part' => false,
        'pay_paypart' => false,
    ];

    /** @return array<string, array{0: array<string, bool>, 1: list<string>}> */
    public static function switches(): array
    {
        return [
            'a method never saved has the defaults' => [[], ['visa', 'mastercard', 'apple-pay', 'google-pay']],
            'the card' => [[...self::ALL_OFF, 'pay_card' => true], ['visa', 'mastercard']],
            'only Apple Pay' => [[...self::ALL_OFF, 'pay_apay' => true], ['apple-pay']],
            'only Google Pay' => [[...self::ALL_OFF, 'pay_gpay' => true], ['google-pay']],
            'Privat24' => [[...self::ALL_OFF, 'pay_privat24' => true], ['privat24']],
            'qr has no common mark' => [[...self::ALL_OFF, 'pay_qr' => true], []],
            'instant instalments' => [[...self::ALL_OFF, 'pay_moment_part' => true], ['privatbank']],
            'pay in parts' => [[...self::ALL_OFF, 'pay_paypart' => true], ['privatbank']],
            'both instalments show one mark' => [[...self::ALL_OFF, 'pay_moment_part' => true, 'pay_paypart' => true], ['privatbank']],
            'a switch turned off takes its mark away' => [['pay_apay' => false], ['visa', 'mastercard', 'google-pay']],
            'nothing on: the cabinet decides, and nobody here knows' => [self::ALL_OFF, []],
        ];
    }

    /**
     * @param  array<string, bool>  $settings
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('switches')]
    public function the_switches_make_the_marks(array $settings, array $expected): void
    {
        $this->assertSame($expected, (new LiqPayCheckoutType)->marks(self::liqpayMethod($settings)));
    }

    #[Test]
    public function every_mark_of_every_switch_is_in_the_registry(): void
    {
        $all = array_map(static fn (): bool => true, self::ALL_OFF);
        $marks = (new LiqPayCheckoutType)->marks(self::liqpayMethod($all));

        $this->assertSame(array_keys(LiqPayCheckoutType::PAYTYPES), array_keys(self::ALL_OFF));
        $this->assertSame(['visa', 'mastercard', 'apple-pay', 'google-pay', 'privat24', 'privatbank'], $marks);
        $this->assertSame($marks, $this->app->make(PaymentMarks::class)->known($marks));
    }
}
