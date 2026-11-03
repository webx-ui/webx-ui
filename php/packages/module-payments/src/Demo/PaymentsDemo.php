<?php

declare(strict_types=1);

namespace WebxUi\Payments\Demo;

use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Localization\Locales;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * A shop that takes money without a single gateway (spec §12): the two built-in methods switched
 * on — on receipt at the shop's point and for cash on delivery, by transfer to fake details
 * («Shop A Ltd», an account of zeros at «Bank A»). The rows are the migration's: the ledger keeps
 * what they held, so `webx:demo --remove` switches them off again.
 */
final readonly class PaymentsDemo
{
    public const string RECIPIENT = 'Shop A Ltd';

    public const string ACCOUNT = "Account 0000 0000 0000 0000\nBank A, code 000000";

    public function __construct(private Locales $locales) {}

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
    }
}
