<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Carbon\CarbonImmutable;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeDecision;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;

/**
 * The base's own events of the exchange journal (spec §20.1, `direction = internal`): no bodies,
 * only the decision — who, the status before and after. A manual mark, a capture or a release
 * asked by the shop, an attempt the poll let expire. A journal that does not write is a line in
 * the log (§20.6), never a failure of what was done.
 */
final readonly class InternalEvents
{
    public function __construct(private ExchangeJournal $journal) {}

    public function write(
        ExchangeKind $kind,
        Payment $payment,
        string $source,
        Outcome $outcome,
        ?PaymentStatus $before,
        ?PaymentStatus $after,
        string $message,
        ?string $trace = null,
        ?string $gateway = null,
    ): void {
        $secrets = ExchangeSecrets::none();
        $id = $this->journal->record(new ExchangeEntry(
            kind: $kind,
            trace: $trace ?? ExchangeContext::newTrace(),
            gateway: $gateway,
            methodKey: $payment->method_key,
            paymentId: $payment->id,
            orderId: $payment->order_id,
            source: $source,
            startedAt: CarbonImmutable::now(),
            durationMs: 0,
        ), $secrets);

        if ($id === null) {
            return;
        }

        $this->journal->decide($id, new ExchangeDecision(
            outcome: $outcome,
            paymentId: $payment->id,
            orderId: $payment->order_id,
            statusBefore: $before?->value,
            statusAfter: $after?->value,
            message: $message,
        ), $secrets);
    }
}
