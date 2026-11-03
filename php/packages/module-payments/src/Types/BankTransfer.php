<?php

declare(strict_types=1);

namespace WebxUi\Payments\Types;

use WebxUi\Payments\Method;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\Words;

/**
 * `store.bank_transfer` — by bank transfer (spec §12.2): only the shop's details — the recipient,
 * the account, the purpose with the order's number, a note on the term. The order awaits payment
 * until the manager marks it; no invoice, no fields of a company (decision 9).
 *
 * The details are copied into the order's block in every language of the site, so a later edit of
 * the method does not change what a buyer was told. The purpose keeps `{number}`: the block is
 * made before the order has a number, and the views put it in (`Instructions`).
 */
final class BankTransfer extends StoreType
{
    public const string KEY = 'store.bank_transfer';

    public const string METHOD = 'bank-transfer';

    /** The fields of the details, in the order a buyer reads them. */
    public const array FIELDS = ['recipient', 'account', 'purpose', 'note'];

    public function key(): string
    {
        return self::KEY;
    }

    public function methodKey(): string
    {
        return self::METHOD;
    }

    public function awaitsPayment(): bool
    {
        return true;
    }

    public function settings(): array
    {
        return [
            self::localizedField('recipient', 'webx-payments::methods.bank_transfer.settings.recipient'),
            // The format of the details is the country's: one field, never parsed.
            self::localizedField('account', 'webx-payments::methods.bank_transfer.settings.account', multiline: true),
            self::localizedField('purpose', 'webx-payments::methods.bank_transfer.settings.purpose'),
            self::localizedField('note', 'webx-payments::methods.bank_transfer.settings.note'),
        ];
    }

    protected function details(Method $method, PaymentContext $context): array
    {
        $instructions = [];

        foreach (self::FIELDS as $field) {
            $own = is_array($method->settings[$field] ?? null) ? $method->settings[$field] : null;
            $words = $field === 'purpose'
                ? Words::everywhere($own, 'webx-payments::methods.bank_transfer.purpose')
                : Words::clean($own);

            if ($words !== []) {
                $instructions[$field] = $words;
            }
        }

        return ['instructions' => $instructions];
    }

    protected function words(): string
    {
        return 'webx-payments::methods.bank_transfer';
    }
}
