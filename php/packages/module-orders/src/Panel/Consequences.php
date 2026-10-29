<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use WebxUi\Admin\ModuleRegistry;
use WebxUi\Cart\Money;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\MilestoneMark;
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
     * @return array{was_paid: bool, money_to_refund: int, money_to_refund_text: string|null, tenders: list<array{label: string, amount: string}>, outcome: string}
     */
    public function cancel(MilestoneMark $mark, string $currency, Money $money): array
    {
        return [
            'was_paid' => $mark->wasPaid,
            'money_to_refund' => $mark->moneyToRefund,
            'money_to_refund_text' => $mark->moneyToRefund > 0 ? $money->format($mark->moneyToRefund, $currency) : null,
            'tenders' => array_map(static fn ($tender): array => [
                'label' => $tender->label,
                'amount' => $money->format($tender->amount, $currency),
            ], $mark->tendersReturned),
            'outcome' => $mark->outcome,
        ];
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
