<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use WebxUi\Admin\ModuleRegistry;
use WebxUi\Cart\Money;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\MilestoneMark;
use WebxUi\Orders\PaymentRecord;
use WebxUi\Orders\RefundReversal;
use WebxUi\Orders\StatusMove;

/**
 * What a move, a cancellation or a batch of them would do, in the panel's words (spec §11.3): the
 * line of the dialog under the choice — «the buyer will see “Shipped”, a message goes through
 * “Buyers”», «the buyer will notice nothing», «sets the mark “Paid”». Read off the service's own
 * dry run, never worked out here, so the dialog and the move cannot disagree.
 */
final class Consequences
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @return array{moved: bool, public_changed: bool, public: string, milestone: array{key: string, label: string}|null, channels: list<string>, refusal: string|null, message: string|null}
     */
    public function move(StatusMove $move, StatusChoices $statuses, string $locale): array
    {
        $public = $statuses->find($move->to->id, $locale)['public']['name'] ?? $move->publicTo->name;

        return [
            'moved' => $move->moved,
            'public_changed' => $move->publicChanged,
            'public' => $public,
            'milestone' => $move->milestone === null ? null : self::milestone($move->milestone),
            'channels' => $this->channels($move->notifications),
            'refusal' => $move->refusal,
            'message' => $move->refusal === null ? null : (string) __('webx-orders::panel.refusals.'.$move->refusal),
        ];
    }

    /**
     * The status the cancellation moves the order into is said the way a move says it (decision
     * 88): its name, what the buyer will see, the channels that write — null when it stays.
     *
     * @return array{was_paid: bool, money_to_refund: int, money_to_refund_text: string|null, tenders: list<array{label: string, amount: string}>, outcome: string, status: array<string, mixed>|null}
     */
    public function cancel(MilestoneMark $mark, string $currency, Money $money): array
    {
        $status = null;

        if ($mark->move !== null) {
            $statuses = new StatusChoices;
            $locale = app()->getLocale();
            $status = [
                'id' => $mark->move->to->id,
                'name' => $statuses->find($mark->move->to->id, $locale)['name'] ?? $mark->move->to->name,
                ...$this->move($mark->move, $statuses, $locale),
            ];
        }

        return [
            'was_paid' => $mark->wasPaid,
            'money_to_refund' => $mark->moneyToRefund,
            'money_to_refund_text' => $mark->moneyToRefund > 0 ? $money->format($mark->moneyToRefund, $currency) : null,
            'tenders' => array_map(static fn ($tender): array => [
                'label' => $tender->label,
                'amount' => $money->format($tender->amount, $currency),
            ], $mark->tendersReturned),
            'outcome' => $mark->outcome,
            'status' => $status,
        ];
    }

    /**
     * What reversing a refund record does (decision 98) — the same answer for the dialog and for
     * `orders_refund_reverse`: what comes off the order, what is left to give back after it, and
     * whether «refunded» comes off. Money in minor units with the string beside it.
     *
     * @return array<string, mixed>
     */
    public function reversal(RefundReversal $reversal, string $currency, Money $money): array
    {
        $amount = static fn (string $key, int $minor): array => [$key => $minor, $key.'_formatted' => $money->format($minor, $currency)];

        return [
            'refund_id' => $reversal->refundId,
            ...$amount('amount', $reversal->amount),
            'tenders' => $reversal->tenders,
            'units' => array_sum($reversal->items),
            ...$amount('refunded_total', $reversal->refundedTotal),
            ...$amount('money_left', $reversal->moneyLeft),
            'tenders_left' => $reversal->tendersLeft,
            'items_left' => $reversal->itemsLeft,
            'milestone_removed' => $reversal->milestoneRemoved === null ? null : self::milestone($reversal->milestoneRemoved),
            'refusal' => $reversal->refusal,
            'message' => $reversal->refusal === null ? null : (string) __('webx-orders::panel.reverse-refusals.'.$reversal->refusal),
            'dry_run' => $reversal->dryRun,
        ];
    }

    /**
     * What a top-up does (§36.3) — the same answer for «Record a top-up» and
     * `orders_payment_record`: the money, the balance before and after it, or why it is refused.
     *
     * @return array<string, mixed>
     */
    public function payment(PaymentRecord $record, Money $money): array
    {
        return [
            ...self::amount('amount', $record->amount, $record->after->currency, $money),
            'reference' => $record->reference,
            'note' => $record->note,
            'at' => $record->at->toAtomString(),
            'before' => self::balance($record->before, $money),
            'after' => self::balance($record->after, $money),
            'refusal' => $record->refusal,
            'message' => $record->refusal === null ? null : (string) __('webx-orders::panel.refusals.'.$record->refusal),
            'dry_run' => $record->dryRun,
        ];
    }

    /**
     * The balance as the panel and an agent read it (§36.3): what came in, what went back, what is
     * still due or over, in minor units with the string beside each.
     *
     * @return array<string, mixed>
     */
    public static function balance(Balance $balance, Money $money): array
    {
        return [
            'kind' => $balance->kind(),
            ...self::amount('paid_total', $balance->paid, $balance->currency, $money),
            ...self::amount('refunded_total', $balance->refunded, $balance->currency, $money),
            ...self::amount('due', $balance->due, $balance->currency, $money),
            ...self::amount('over', $balance->over, $balance->currency, $money),
            ...self::amount('tenders_over', $balance->tendersOver, $balance->currency, $money),
            'balance' => $balance->due - $balance->over,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private static function amount(string $key, int $minor, string $currency, Money $money): array
    {
        return [$key => $minor, $key.'_formatted' => $money->format($minor, $currency)];
    }

    /**
     * @return array{key: string, label: string}
     */
    public static function milestone(Milestone $milestone): array
    {
        return ['key' => $milestone->value, 'label' => $milestone->label()];
    }

    /**
     * The names of the channels a move would fire, when the notifications are installed; without
     * them a status keeps the ids and nothing is sent, so nothing is named.
     *
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function channels(array $ids): array
    {
        if ($ids === [] || ! $this->modules->has('notifications') || ! class_exists(Channel::class)) {
            return [];
        }

        return Channel::query()
            ->whereIn('id', $ids)
            ->where('enabled', true)
            ->orderBy('id')
            ->pluck('name')
            ->map(strval(...))
            ->values()
            ->all();
    }
}
