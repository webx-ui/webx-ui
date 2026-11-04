<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Availability;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\Contracts\ValidatesSettings;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;
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
use WebxUi\Payments\Presentation;
use WebxUi\Payments\StartNeeds;
use WebxUi\Payments\StartRequest;
use WebxUi\Payments\Words;

/**
 * `liqpay.checkout` — the page of LiqPay (spec §6). One type, any number of methods: what the page
 * offers is the method's seven switches, each with its own keys (decisions 3, 15).
 *
 * The talk with LiqPay comes by the stages of the plan (§19): the notification with stage 2, the
 * page and the statuses with stage 3, the hold with stage 4. Until then those calls refuse.
 */
final class LiqPayCheckoutType implements ChargesOnline, ValidatesSettings
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

    /** The fields a hold rules out: what a hold means with instalments is not known (decision 17). */
    private const array INSTALMENTS = ['pay_moment_part', 'pay_paypart'];

    private const string NOT_YET = 'The payments of LiqPay are not there yet';

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

            if ($name === 'pay_paypart') {
                $field['help'] = LiqPayGateway::line('paytypes.paypart_help');
            }

            if (in_array($name, self::INSTALMENTS, true)) {
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

        $on = array_filter(self::PAYTYPES, static fn (array $paytype, string $name): bool => (bool) ($settings[$name] ?? $paytype[1]), ARRAY_FILTER_USE_BOTH);

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

    public function start(Method $method, Credentials $credentials, StartRequest $request, GatewayHttp $http): Handoff
    {
        throw GatewayUnavailable::because(self::NOT_YET);
    }

    public function fetchStatus(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus
    {
        throw GatewayUnavailable::because(self::NOT_YET);
    }

    public function readNotification(Method $method, Credentials $credentials, Request $request, GatewayHttp $http): Notification
    {
        throw InvalidNotification::malformed(self::NOT_YET);
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
