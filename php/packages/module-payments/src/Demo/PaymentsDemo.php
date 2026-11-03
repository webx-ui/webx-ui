<?php

declare(strict_types=1);

namespace WebxUi\Payments\Demo;

use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Localization\Locales;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Testing\FakeGateway;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * A shop that takes money without a single gateway (spec §12): the two built-in methods switched
 * on — on receipt at the shop's point and for cash on delivery, by transfer to fake details
 * («Shop A Ltd», an account of zeros at «Bank A»). The rows are the migration's: the ledger keeps
 * what they held, so `webx:demo --remove` switches them off again.
 *
 * Where the fake «gateway A» is on (`WEBX_PAYMENTS_FAKE_GATEWAY=true` on a development site, §21),
 * its three methods come too, with its test key: the checkout has a card to pay with, and the
 * journal of the exchange something in it. `--remove` takes the ones it made away again.
 */
final readonly class PaymentsDemo
{
    public const string RECIPIENT = 'Shop A Ltd';

    public const string ACCOUNT = "Account 0000 0000 0000 0000\nBank A, code 000000";

    public function __construct(private Locales $locales, private Gateways $gateways) {}

    public function seed(DemoLedger $ledger): void
    {
        $everywhere = fn (string $text): array => array_fill_keys($this->locales->codes(), $text);

        foreach ([OnReceipt::METHOD => [], BankTransfer::METHOD => [
            'recipient' => $everywhere(self::RECIPIENT),
            'account' => $everywhere(self::ACCOUNT),
        ]] as $key => $settings) {
            $method = PaymentMethod::query()->where('key', $key)->first();

            if ($method === null) {
                continue;
            }

            $ledger->changed($method, ['enabled', 'settings']);
            $method->forceFill(['enabled' => true, 'settings' => [...$method->settings, ...$settings]])->save();
        }

        if ($this->gateways->has(FakeGateway::KEY)) {
            $this->gatewayA($ledger);
        }
    }

    /** Gateway A's methods, switched on: the ones the site had are written down before, the rest are new. */
    private function gatewayA(DemoLedger $ledger): void
    {
        $had = PaymentMethod::query()->whereIn('key', FakeGatewaySite::METHODS)->get()->keyBy('key');

        foreach ($had as $method) {
            $ledger->changed($method, ['enabled', 'credentials']);
        }

        foreach (FakeGatewaySite::methods() as $method) {
            if (! $had->has($method->key)) {
                $ledger->created($method);
            }
        }
    }
}
