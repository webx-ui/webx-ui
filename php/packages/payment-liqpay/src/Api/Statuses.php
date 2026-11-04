<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Api;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Lang;
use WebxUi\Payment\LiqPay\LiqPayGateway;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;

/**
 * The statuses of LiqPay in the base's terms (spec §9.2).
 *
 * `reversed` means another thing at an attempt on hold, and only `fetchStatus()` sees the attempt:
 * a notification never reaches this table with it (§8.3). The table says what it is everywhere else.
 */
final class Statuses
{
    /** @var array<string, Reported> */
    public const array TABLE = [
        'success' => Reported::Paid,
        'wait_compensation' => Reported::Paid,
        'wait_accept' => Reported::Paid,
        'wait_reserve' => Reported::Paid,
        'hold_wait' => Reported::Authorized,
        'failure' => Reported::Failed,
        'error' => Reported::Failed,
        'reversed' => Reported::Refunded,
        'try_again' => Reported::Pending,
        '3ds_verify' => Reported::Pending,
        'captcha_verify' => Reported::Pending,
        'cvv_verify' => Reported::Pending,
        'ivr_verify' => Reported::Pending,
        'otp_verify' => Reported::Pending,
        'password_verify' => Reported::Pending,
        'phone_verify' => Reported::Pending,
        'pin_verify' => Reported::Pending,
        'senderapp_verify' => Reported::Pending,
        'wait_qr' => Reported::Pending,
        'wait_sender' => Reported::Pending,
        'processing' => Reported::Pending,
        'prepared' => Reported::Pending,
        'cash_wait' => Reported::Pending,
        'invoice_wait' => Reported::Pending,
        'wait_secure' => Reported::Pending,
        'wait_card' => Reported::Pending,
        'wait_lc' => Reported::Pending,
        'subscribed' => Reported::Pending,
        'unsubscribed' => Reported::Pending,
    ];

    /** Days a hold is guaranteed: LiqPay does not say, the card decides (§10). */
    public const int HOLD_DAYS = 7;

    public const int HOLD_DAYS_VISA = 10;

    /** The statuses that carry a word of their own in the reason (§9.2). */
    private const array SAID = ['wait_accept', 'wait_reserve', 'subscribed', 'unsubscribed'];

    /** The fields of `meta` (§9.1). The base does not read them yet, so nothing leans on them. */
    private const array META = ['paytype', 'sender_card_mask2', 'sender_card_type', 'is_3ds', 'receiver_commission'];

    /** @param  PaymentStatus|null  $attempt  what the attempt is now; a notification does not know (§8.3) */
    public static function read(Answer $answer, ?PaymentStatus $attempt = null): GatewayStatus
    {
        $status = (string) $answer->status();
        $currency = $answer->text('currency');
        $reported = self::reported($status, $answer, $attempt);

        return new GatewayStatus(
            status: $reported,
            gatewayRef: $answer->paymentId(),
            amount: self::minor($answer->fields['amount'] ?? null, $currency),
            currency: $currency,
            paidAt: self::date($answer->fields['end_date'] ?? null),
            reason: self::reason($status, $answer),
            refundedAmount: self::minor($answer->fields['refund_amount'] ?? null, $currency),
            holdExpiresAt: $reported === Reported::Authorized ? self::holdEnd($answer) : null,
            meta: array_filter(
                array_intersect_key($answer->fields, array_flip(self::META)),
                static fn (mixed $value): bool => is_scalar($value),
            ),
            // The page keeps the payment open for another card, the buyer who left it is told now.
            declined: $status === 'try_again',
        );
    }

    private static function reported(string $status, Answer $answer, ?PaymentStatus $attempt): Reported
    {
        if ($status === 'failure' && $answer->errorCode() === 'expired') {
            return Reported::Expired;
        }

        if ($status === 'reversed' && $attempt === PaymentStatus::Authorized) {
            return Reported::Cancelled;
        }

        if ($status === 'success' && $attempt === PaymentStatus::Paid && (float) ($answer->fields['refund_amount'] ?? 0) > 0) {
            return Reported::Refunded;
        }

        return self::TABLE[$status] ?? Reported::Pending;
    }

    /**
     * The amount comes as a JSON number, a float by now. The rounding only takes off the error of
     * its representation: the amount of the order, not `amount_debit`, stays in its currency (§9.1).
     */
    private static function minor(mixed $amount, ?string $currency): ?int
    {
        if (! is_numeric($amount) || $currency === null) {
            return null;
        }

        return GatewayAmount::fromDecimal(number_format((float) $amount, GatewayAmount::exponent($currency), '.', ''), $currency);
    }

    /** No date in the answer — null, and the base counts `maxHoldDays()` from now. */
    private static function holdEnd(Answer $answer): ?CarbonImmutable
    {
        $days = strtolower((string) $answer->text('sender_card_type')) === 'visa' ? self::HOLD_DAYS_VISA : self::HOLD_DAYS;

        return self::date($answer->fields['create_date'] ?? null)?->addDays($days);
    }

    /** Milliseconds. */
    private static function date(mixed $value): ?CarbonImmutable
    {
        return is_int($value) && $value > 0 ? CarbonImmutable::createFromTimestampMs($value) : null;
    }

    private static function reason(string $status, Answer $answer): ?string
    {
        if (! isset(self::TABLE[$status])) {
            return (string) __(LiqPayGateway::line('status.unknown'), ['status' => $status]);
        }

        if (in_array($status, self::SAID, true)) {
            return LiqPayGateway::word('status.'.$status);
        }

        return self::wordOf($answer->errorCode()) ?? $answer->text('err_description');
    }

    /** The dictionary's word for a code of LiqPay, if it has one. */
    public static function wordOf(?string $code): ?string
    {
        // A code with a dot would read another branch of the dictionary.
        if ($code !== null && preg_match('/^\w+$/', $code) === 1 && Lang::has(LiqPayGateway::line('errors.'.$code))) {
            return LiqPayGateway::word('errors.'.$code);
        }

        return null;
    }
}
