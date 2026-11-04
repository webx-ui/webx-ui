<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payment\LiqPay\Api\Answer;
use WebxUi\Payment\LiqPay\Api\Client;
use WebxUi\Payment\LiqPay\Api\Signature;
use WebxUi\Payment\LiqPay\Api\Statuses;
use WebxUi\Payments\Availability;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Contracts\ProvidesPaymentMarks;
use WebxUi\Payments\Contracts\ValidatesSettings;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayRefused;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\InvalidNotification;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Notification;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Presentation;
use WebxUi\Payments\Reported;
use WebxUi\Payments\StartNeeds;
use WebxUi\Payments\StartRequest;
use WebxUi\Payments\Words;

/**
 * `liqpay.checkout` — the page of LiqPay (spec §6). One type, any number of methods: what the page
 * offers is the method's seven switches, each with its own keys (decisions 3, 15). A method with the
 * base's switch `hold` takes the money in two steps (§10).
 */
final class LiqPayCheckoutType implements CapturesPayments, ProvidesPaymentMarks, ValidatesSettings
{
    public const string KEY = 'liqpay.checkout';

    public const string METHOD = 'liqpay-checkout';

    /** Field of the method => its value of `paytypes` and whether it is on by default, in order. */
    public const array PAYTYPES = [
        'pay_card' => ['card', true],
        'pay_apay' => ['apay', true],
        'pay_gpay' => ['gpay', true],
        'pay_privat24' => ['privat24', false],
        'pay_qr' => ['qr', false],
        'pay_moment_part' => ['moment_part', false],
        'pay_paypart' => ['paypart', false],
    ];

    /** Field of the method => the marks it shows: `qr` has no common mark (groups spec §3.5). */
    private const array MARKS = [
        'pay_card' => ['visa', 'mastercard'],
        'pay_apay' => ['apple-pay'],
        'pay_gpay' => ['google-pay'],
        'pay_privat24' => ['privat24'],
        'pay_qr' => [],
        'pay_moment_part' => ['privatbank'],
        'pay_paypart' => ['privatbank'],
    ];

    /** The fields a hold rules out: what a hold means with instalments is not known (decision 17). */
    private const array INSTALMENTS = ['pay_moment_part', 'pay_paypart'];

    /**
     * Subscriptions and tokens (§21). A list of what is not ours rather than of what is: the action
     * of a refund is not known, and it has to reach the poke (§3.3).
     */
    private const array NOT_PAYMENTS = ['subscribe', 'unsubscribe', 'regular', 'recurring', 'recurringbytoken'];

    public function key(): string
    {
        return self::KEY;
    }

    public function provider(): string
    {
        return LiqPayGateway::KEY;
    }

    public function kind(): Kind
    {
        return Kind::Online;
    }

    public function awaitsPayment(): bool
    {
        return true;
    }

    public function settings(): array
    {
        $fields = [];

        foreach (self::PAYTYPES as $name => [$paytype, $default]) {
            $field = ['name' => $name, 'type' => 'switch', 'label' => LiqPayGateway::line("paytypes.{$paytype}"), 'default' => $default];

            if ($name === 'pay_card') {
                $field['help'] = LiqPayGateway::line('paytypes.card_help');
            }

            if (in_array($name, self::INSTALMENTS, true)) {
                $field['help'] = LiqPayGateway::line('paytypes.service_help');
                $field['off_with'] = 'hold';
                $field['off_help'] = LiqPayGateway::line('paytypes.hold_cards_only');
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Without a single switch `paytypes` is not sent and the page shows all the cabinet has,
     * instalments too: so a hold needs a choice as much as it needs no instalments (§6.1).
     */
    public function validateSettings(array $settings): array
    {
        if (! ($settings['hold'] ?? false)) {
            return [];
        }

        $on = self::paytypes($settings);

        if ($on === []) {
            return ['pay_card' => LiqPayGateway::line('paytypes.hold_needs_choice')];
        }

        $refused = [];

        foreach (self::INSTALMENTS as $name) {
            if (isset($on[$name])) {
                $refused[$name] = LiqPayGateway::line('paytypes.hold_cards_only');
            }
        }

        return $refused;
    }

    /**
     * What the switches offer. With none on `paytypes` is not sent and the page shows what the
     * cabinet has, which nobody here knows: no marks then (groups spec §3.5).
     */
    public function marks(Method $method): array
    {
        $marks = [];

        foreach (array_keys(self::paytypes($method->settings)) as $name) {
            $marks = [...$marks, ...self::MARKS[$name]];
        }

        return array_values(array_unique($marks));
    }

    /**
     * What is switched on, in the order of the table: a switch never saved keeps its default.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string> field => value of `paytypes`
     */
    private static function paytypes(array $settings): array
    {
        $on = [];

        foreach (self::PAYTYPES as $name => [$paytype, $default]) {
            if ($settings[$name] ?? $default) {
                $on[$name] = $paytype;
            }
        }

        return $on;
    }

    /** The page speaks Ukrainian and English only: `ru` would silently give Ukrainian (§5.2). */
    private static function language(Method $method, string $locale): string
    {
        $language = $method->settings['language'] ?? 'auto';

        if ($language === 'uk' || $language === 'en') {
            return $language;
        }

        return $locale === 'uk' ? 'uk' : 'en';
    }

    /** @return list<string> PLN and the rest are refused with `err_wrong_currency` (§3.2) */
    public function currencies(): array
    {
        return ['UAH', 'USD', 'EUR'];
    }

    public function available(Method $method, PaymentContext $context): Availability
    {
        return Availability::yes();
    }

    public function present(Method $method, PaymentContext $context): Presentation
    {
        return new Presentation($method->title($context->locale) ?? LiqPayGateway::word('gateway.title', $context->locale), $method->description($context->locale));
    }

    public function snapshot(Method $method, PaymentContext $context): PaymentSnapshot
    {
        $titles = Words::clean($method->titles);

        return new PaymentSnapshot($method->key, self::KEY, Kind::Online, $titles !== [] ? $titles : [$context->locale => LiqPayGateway::word('gateway.title', $context->locale)]);
    }

    /** The page asks the buyer for everything itself (§7). */
    public function needs(): StartNeeds
    {
        return new StartNeeds;
    }

    /** The signature covers all of `data` (§8.1). */
    public function trustsNotifications(): bool
    {
        return true;
    }

    /**
     * Only signs the form: LiqPay hears of the payment when the buyer sends the card, so there is
     * no request here and no `payment_id` yet (§7). The term goes always: without it a forgotten
     * payment has no end (decision 13).
     */
    public function start(Method $method, Credentials $credentials, StartRequest $request, GatewayHttp $http): Handoff
    {
        $expiresAt = $request->expiresAt ?? CarbonImmutable::now()->addMinutes(LiqPayGateway::EXPIRES_MINUTES);
        $paytypes = self::paytypes($method->settings);

        $fields = [
            'amount' => GatewayAmount::toDecimal($request->amount, $request->currency),
            'currency' => $request->currency,
            'description' => $request->description,
            'order_id' => $request->uuid,
            'result_url' => $request->returnUrl,
            'server_url' => $request->notifyUrl,
            'language' => self::language($method, $request->locale),
            'expired_date' => $expiresAt->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ];

        if ($paytypes !== []) {
            $fields['paytypes'] = implode(',', $paytypes);
        }

        $form = Client::for($http, Keys::of($method, $credentials))->form($request->hold ? 'hold' : 'pay', $fields);

        return Handoff::post((string) config('webx-payment-liqpay.checkout_url'), $form, null, $expiresAt);
    }

    /**
     * A failed request and a payment look alike, `payment_id` tells them apart (§9.1). No such
     * payment is the buyer who has not sent a card: the attempt stays open until its term (§9.3).
     */
    public function fetchStatus(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus
    {
        $answer = Client::for($http, Keys::of($method, $credentials))->call('status', ['order_id' => $payment->uuid]);

        if ($answer->isPayment()) {
            return Statuses::read($answer, $payment->status);
        }

        if ($answer->errorCode() === 'payment_not_found') {
            return new GatewayStatus(Reported::Pending, notStarted: true);
        }

        throw GatewayUnavailable::because(Statuses::wordOf($answer->errorCode()) ?? LiqPayGateway::word('connection.no_answer'));
    }

    /** The smaller of the two guarantees: the poll warns by it when an answer has no date (§10). */
    public function maxHoldDays(): int
    {
        return Statuses::HOLD_DAYS;
    }

    public function capturesPartially(): bool
    {
        return true;
    }

    /**
     * The rest of a part LiqPay lets go itself. `payment_err_status` is also the second call after
     * an answer that got lost: only the status tells it from a hold that is gone (§10).
     */
    public function capture(Method $method, Credentials $credentials, Payment $payment, int $amount, GatewayHttp $http): GatewayStatus
    {
        $answer = Client::for($http, Keys::of($method, $credentials))->call('hold_completion', [
            'order_id' => $payment->uuid,
            'amount' => GatewayAmount::toDecimal($amount, $payment->currency),
        ]);

        if ($answer->isPayment()) {
            $status = Statuses::read($answer, $payment->status);

            // A capture that failed leaves the hold in place: the attempt must not end with it.
            return $status->status === Reported::Failed ? throw self::refused($answer) : $status;
        }

        // The same code as a part of a release, and here it is the currency: a hold in EUR or USD
        // is debited in hryvnias, and LiqPay takes only the whole of it, whatever the request says.
        if ($answer->errorCode() === 'wrong_amount_currency') {
            throw GatewayRefused::because(LiqPayGateway::word('errors.part_in_hryvnias'));
        }

        if ($answer->errorCode() === 'payment_err_status') {
            $status = $this->fetchStatus($method, $credentials, $payment, $http->kind(ExchangeKind::Status));

            if ($status->status === Reported::Paid) {
                return $status;
            }
        }

        throw self::refused($answer);
    }

    /**
     * `refund` on the whole sum is the only way to let a hold go, and LiqPay refuses a part (§3.2).
     * At a paid attempt the same request would give the money back, which is not in v1 (§10).
     */
    public function void(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus
    {
        if ($payment->status !== PaymentStatus::Authorized) {
            throw GatewayRefused::because(LiqPayGateway::word('errors.payment_err_status'));
        }

        $answer = Client::for($http, Keys::of($method, $credentials))->call('refund', [
            'order_id' => $payment->uuid,
            'amount' => GatewayAmount::toDecimal((int) ($payment->authorized_amount ?? $payment->amount), $payment->currency),
            'currency' => $payment->currency,
        ]);

        if ($answer->status() !== 'reversed') {
            throw self::refused($answer);
        }

        return Statuses::read($answer, $payment->status);
    }

    /** Words for the manager, never the body. */
    private static function refused(Answer $answer): GatewayRefused
    {
        return GatewayRefused::because(Statuses::wordOf($answer->errorCode()) ?? LiqPayGateway::word('errors.refused'));
    }

    /**
     * The steps of §8.1. The signature is checked on the string `data` as it came: decoding it and
     * encoding it again would give another string.
     */
    public function readNotification(Method $method, Credentials $credentials, Request $request, GatewayHttp $http): Notification
    {
        $data = $request->post('data');
        $signature = $request->post('signature');

        if (! is_string($data) || $data === '' || ! is_string($signature) || $signature === '') {
            throw InvalidNotification::malformed();
        }

        $private = Keys::of($method, $credentials)->privateKey();
        $answer = Answer::decode($data);

        if ($private === null || ! Signature::verify($data, $signature, $private)) {
            throw InvalidNotification::signature(uuid: $answer?->orderId());
        }

        if ($answer === null) {
            throw InvalidNotification::malformed();
        }

        $eventKey = Notification::keyOf($data);

        if (in_array($answer->action(), self::NOT_PAYMENTS, true) || in_array($answer->status(), ['subscribed', 'unsubscribed'], true)) {
            $what = $answer->action().', '.$answer->status();

            return Notification::unrelated($eventKey, (string) __(LiqPayGateway::line('status.not_a_payment'), ['what' => $what]), $answer->orderId(), $answer->paymentId());
        }

        // The same word means another thing at an attempt on hold, and only the poll sees it (§8.3).
        if ($answer->status() === 'reversed' || (float) ($answer->fields['refund_amount'] ?? 0) > 0) {
            return Notification::poke($eventKey, $answer->orderId(), $answer->paymentId());
        }

        return Notification::status($eventKey, $answer->orderId(), $answer->paymentId(), Statuses::read($answer));
    }

    /** What LiqPay expects is not documented: an empty 200, until a live one says otherwise (§8.2). */
    public function acceptResponse(Method $method, Credentials $credentials, Notification $notification): Response
    {
        return new HttpResponse('', 200);
    }

    /** A repeat will not mend a bad signature, and the journal says the keys are wrong (§8.2). */
    public function rejectResponse(Method $method, Credentials $credentials, InvalidNotification $e): Response
    {
        return new HttpResponse('', 400);
    }
}
