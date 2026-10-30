<?php

declare(strict_types=1);

namespace WebxUi\Orders\Notifications;

use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\Mail\MailRoutes;
use WebxUi\Admin\Support\Authors;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\Refusal as NotificationsRefusal;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\NotifyRefused;
use WebxUi\Orders\NotifyResult;
use WebxUi\Orders\OrderSettings;

/**
 * The orders' side of `webx-ui/module-notifications` (spec §10): which channels an event goes to,
 * the sending, and what came of it on an order — the block «Notifications» of the card and
 * `orders_history`.
 *
 * The dependency is soft. The package is a `suggest`, so every method answers for a site without
 * it first — nothing, nobody, false — and only then names a class of it. `available()` is the one
 * question: the package is there, its provider ran, and the events are declared.
 */
final readonly class OrderChannels
{
    public const string OPTION = 'notify_channels';

    /** The addressee a message by hand goes to (decision 99): the order's e-mail, the buyer. */
    public const string ADDRESSEE = 'customer_email';

    /** The events the buyer may be told about again by hand, in the order of the card's dialog. */
    public const array BY_HAND = [
        OrderEvents::PLACED,
        OrderEvents::PUBLIC_STATUS,
        OrderEvents::PAID,
        OrderEvents::PAYMENT_FAILED,
        OrderEvents::RECEIVED,
        OrderEvents::CANCELLED,
        OrderEvents::REFUNDED,
        OrderEvents::PAYMENT_REQUESTED,
    ];

    public function __construct(
        private Container $container,
        private OrderSettings $settings,
    ) {}

    public function available(): bool
    {
        return class_exists(NotificationEvents::class)
            && $this->container->bound(NotificationEvents::class)
            && $this->container->make(NotificationEvents::class)->has(OrderEvents::PLACED);
    }

    /**
     * The channels a milestone's event goes to (§10.1): the setting `orders.notify.*`.
     *
     * @return list<int>
     */
    public function forEvent(string $event): array
    {
        $key = OrderEvents::SETTINGS[$event] ?? null;

        return $key === null ? [] : self::ids($this->settings->get($key));
    }

    /**
     * Whether a milestone's event writes to the buyer: a channel of it is on and sends to the
     * order's own e-mail — what «Thank you» promises the buyer (§26).
     */
    public function reachesBuyer(string $event): bool
    {
        $ids = $this->forEvent($event);

        if ($ids === [] || ! $this->available()) {
            return false;
        }

        foreach (Channel::query()->whereKey($ids)->where('enabled', true)->get(['id', 'recipients']) as $channel) {
            foreach ((array) $channel->recipients as $recipient) {
                if (is_array($recipient) && ($recipient['variable'] ?? null) === 'customer_email') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The channels of a public status — none once it is deleted (§5.6).
     *
     * @return list<int>
     */
    public static function ofPublic(?PublicStatus $status): array
    {
        if ($status === null || $status->trashed()) {
            return [];
        }

        return self::ids($status->options[self::OPTION] ?? []);
    }

    /**
     * The ids as stored: whole, positive, each once.
     *
     * @return list<int>
     */
    public static function ids(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $ids = [];

        foreach ($input as $id) {
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                $ids[(int) $id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * What may be kept: the channels that exist, in the bin included — one put there comes back,
     * and until then the log says why nothing went. Without the module the list is kept as it
     * came: removing the package must not wipe a choice somebody made.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function existing(array $ids): array
    {
        if ($ids === [] || ! $this->available()) {
            return $ids;
        }

        $known = Channel::query()->withTrashed()->whereKey($ids)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        return array_values(array_filter($ids, static fn (int $id): bool => in_array($id, $known, true)));
    }

    /**
     * Tell the channels about an order. Nothing is thrown — `send()` logs a channel that cannot
     * take it as skipped; without the module, or with no channel, nothing happens.
     *
     * @param  list<int>  $channels
     * @param  array<string, string|list<array<string, string>>>  $extra
     */
    public function send(string $event, array $channels, OrderData $order, array $extra = []): int
    {
        if ($channels === [] || ! $this->available()) {
            return 0;
        }

        $subject = Order::query()->find($order->id);

        $this->container->make(Notifications::class)->send(
            $event,
            $channels,
            $this->container->make(OrderValues::class)->of($order, $extra),
            subject: $subject,
            locale: $order->locale,
        );

        return count($channels);
    }

    /**
     * The log of the channels about one order, newest first — the card's block «Notifications».
     * A row sent by hand (decision 99) names who sent it, in one query for the log.
     *
     * @return list<array<string, mixed>>
     */
    public function deliveries(Order $order, mixed $reader = null): array
    {
        if (! $this->available() || ! $order->exists) {
            return [];
        }

        $rows = $this->container->make(Notifications::class)->deliveriesFor($order)
            ->where('is_test', false)
            ->limit(100)
            ->get();
        $names = Authors::names($reader, $rows->pluck('manual_by')->all());

        return $rows
            ->map(static fn (Delivery $row): array => [
                'id' => (int) $row->id,
                'event' => $row->event,
                'event_label' => in_array($row->event, OrderEvents::keys(), true)
                    ? (string) __('webx-orders::notifications.events.'.substr($row->event, strlen('orders.')))
                    : null,
                'channel_id' => $row->channel_id,
                'channel' => $row->channel_name,
                'provider' => $row->provider,
                'recipient' => $row->recipient,
                'recipient_label' => $row->recipient_label,
                'status' => $row->status,
                'trap' => $row->trap,
                // In the notifications module's own words.
                'status_label' => $row->status === Delivery::SENT && $row->trap !== null
                    ? app(MailRoutes::class)->trappedLabel()
                    : (string) __('webx-notifications::module.statuses.'.$row->status),
                'reason' => $row->reason,
                'reason_label' => $row->reason === null ? null : (string) __('webx-notifications::module.reasons.'.$row->reason),
                'error' => $row->error,
                'created_at' => $row->created_at?->toAtomString(),
                'sent_at' => $row->sent_at?->toAtomString(),
                // «Send again» (decision 99): a row that went, failed or got stuck, to somebody.
                'resendable' => $row->resendable(),
                'manual' => $row->manual,
                'manual_by' => $row->manual_by !== null ? ($names[$row->manual_by] ?? null) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * The events the buyer may be told about again by hand (decision 99), each with whether the
     * order gives it a reason now — it was placed; it stands in a public status; it was paid, its
     * payment failed, it was received, cancelled, given back — and the channels of it that write
     * to the buyer's e-mail. «Send the buyer a letter» of the card offers these.
     *
     * @return array{email: string|null, reason: string|null, events: list<array{key: string, label: string, basis: bool, channels: list<array{id: int, name: string, provider: string}>, available: bool, reason: string|null}>}
     */
    public function buyerEvents(Order $order): array
    {
        $email = $order->customer_email !== null && trim($order->customer_email) !== '' ? $order->customer_email : null;
        $available = $this->available();
        $ids = [];

        foreach (self::BY_HAND as $key) {
            $ids[$key] = $available && $this->basis($order, $key) ? $this->channelsOf($order, $key) : null;
        }

        // Every event's channels in one query; which of them write to the buyer is told per event.
        $all = $available ? Channel::query()->whereKey(array_merge(...array_values(array_filter($ids))) ?: [0])->orderBy('id')->get() : collect();
        $events = [];

        foreach (self::BY_HAND as $key) {
            $basis = $this->basis($order, $key);
            $channels = $ids[$key] === null ? [] : $all
                ->filter(fn (Channel $channel): bool => in_array((int) $channel->id, $ids[$key], true) && $this->notifications()->writesTo($channel, $key, self::ADDRESSEE))
                ->map(static fn (Channel $channel): array => ['id' => (int) $channel->id, 'name' => $channel->name, 'provider' => $channel->provider])
                ->values()
                ->all();
            $reason = match (true) {
                ! $basis => NotifyRefused::NO_BASIS,
                $channels === [] => NotifyRefused::NO_CHANNEL,
                $email === null => NotifyRefused::NO_EMAIL,
                default => null,
            };

            $events[] = [
                'key' => $key,
                'label' => (string) __('webx-orders::notifications.events.'.substr($key, strlen('orders.'))),
                'basis' => $basis,
                'channels' => $channels,
                'available' => $reason === null,
                'reason' => $reason,
            ];
        }

        $any = array_filter($events, static fn (array $event): bool => $event['channels'] !== []) !== [];

        return [
            'email' => $email,
            'reason' => match (true) {
                ! $this->available() => NotifyRefused::UNAVAILABLE,
                ! $any => NotifyRefused::NO_CHANNEL,
                $email === null => NotifyRefused::NO_EMAIL,
                default => null,
            },
            'events' => $events,
        ];
    }

    /**
     * Tell the buyer of an event again (decision 99): its channels that write to the buyer, to the
     * order's e-mail as it is now, with the order's values now and the event's own — the reference
     * of the payment, the reason of the cancellation. A dry run answers each channel's message.
     *
     * @throws NotifyRefused
     */
    public function notifyBuyer(Order $order, string $event, ?int $by, bool $dryRun): NotifyResult
    {
        if (! $this->available()) {
            throw new NotifyRefused(NotifyRefused::UNAVAILABLE);
        }

        if (! in_array($event, self::BY_HAND, true) || ! $this->basis($order, $event)) {
            throw new NotifyRefused(NotifyRefused::NO_BASIS, event: $event);
        }

        if ($order->customer_email === null || trim($order->customer_email) === '') {
            throw new NotifyRefused(NotifyRefused::NO_EMAIL);
        }

        $notifications = $this->notifications();
        $channels = $notifications->addressed($this->channelsOf($order, $event), $event, self::ADDRESSEE);

        if ($channels === []) {
            throw new NotifyRefused(NotifyRefused::NO_CHANNEL);
        }

        $values = $this->container->make(OrderValues::class)->of(OrderData::of(Order::query()->findOrFail($order->id)), $this->extra($order, $event));

        try {
            $rows = $notifications->sendByHand($event, array_map(static fn (Channel $channel): int => (int) $channel->id, $channels), self::ADDRESSEE, $values, $order, $order->locale, $by, $dryRun);
        } catch (NotificationsRefusal $refusal) {
            throw new NotifyRefused(NotifyRefused::REFUSED, $refusal->getMessage());
        }

        $previews = [];

        if ($dryRun) {
            foreach ($channels as $channel) {
                $previews[] = [
                    'channel' => $channel->name,
                    'provider' => $channel->provider,
                    'recipient' => (string) $values['customer_email'],
                    ...$notifications->render($channel, $values, $order->locale),
                ];
            }
        }

        return new NotifyResult($event, $dryRun, array_map(self::row(...), $rows), $previews);
    }

    /**
     * Send a row of the order's log again (decision 99): the same message to the same recipient,
     * a new row marked manual. Only a row about this order.
     *
     * @throws NotifyRefused
     */
    public function resend(Order $order, int $delivery, ?int $by, bool $dryRun): NotifyResult
    {
        if (! $this->available()) {
            throw new NotifyRefused(NotifyRefused::UNAVAILABLE);
        }

        $row = Delivery::query()->whereKey($delivery)->where('subject_type', $order->getMorphClass())->where('subject_id', $order->getKey())->first();

        if (! $row instanceof Delivery) {
            throw new NotifyRefused(NotifyRefused::FOREIGN);
        }

        try {
            $sent = $this->notifications()->resend($row, $by, $dryRun);
        } catch (NotificationsRefusal $refusal) {
            throw new NotifyRefused(NotifyRefused::REFUSED, $refusal->getMessage());
        }

        return new NotifyResult($row->event, $dryRun, [self::row($sent)], resentFrom: (int) $row->id);
    }

    /** Whether the order gives an event a reason to be told of now. */
    private function basis(Order $order, string $event): bool
    {
        return match ($event) {
            OrderEvents::PLACED => true,
            OrderEvents::PUBLIC_STATUS => $this->publicOf($order) !== null,
            OrderEvents::PAID => $order->paid_at !== null,
            OrderEvents::PAYMENT_FAILED => $order->payment_failed_at !== null && $order->paid_at === null,
            OrderEvents::RECEIVED => $order->received_at !== null,
            OrderEvents::CANCELLED => $order->cancelled_at !== null,
            OrderEvents::REFUNDED => $order->refunded_total > 0,
            // The buyer owes now (§40.2): `due` is 0 once cancelled.
            OrderEvents::PAYMENT_REQUESTED => Balance::of($order)->due > 0,
            default => false,
        };
    }

    /**
     * The channels of an event of this order: the setting of a milestone's, the current public
     * status's for `orders.public_status`.
     *
     * @return list<int>
     */
    private function channelsOf(Order $order, string $event): array
    {
        return $event === OrderEvents::PUBLIC_STATUS ? self::ofPublic($this->publicOf($order)) : $this->forEvent($event);
    }

    private function publicOf(Order $order): ?PublicStatus
    {
        $status = OrderStatus::withTrashed()->find($order->status_id);
        $public = $status instanceof OrderStatus ? PublicStatus::withTrashed()->find($status->public_status_id) : null;

        return $public instanceof PublicStatus && ! $public->trashed() ? $public : null;
    }

    /**
     * The values of one event beyond the order's (§10.2), as the event brought them: the mark's
     * reference and note, the latest failure, what was given back.
     *
     * @return array<string, string>
     */
    private function extra(Order $order, string $event): array
    {
        $mark = static fn (string $milestone): ?OrderMilestone => OrderMilestone::query()->where('order_id', $order->id)->where('milestone', $milestone)->first();

        return match ($event) {
            OrderEvents::PAID => ['reference' => (string) $mark(Milestone::Paid->value)?->reference],
            OrderEvents::RECEIVED => ['reference' => (string) $mark(Milestone::Received->value)?->reference],
            OrderEvents::CANCELLED => ['reason' => (string) $mark(Milestone::Cancelled->value)?->note],
            OrderEvents::PAYMENT_FAILED => ['reason' => (string) StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::PAYMENT_FAILED)->orderByDesc('id')->value('note')],
            OrderEvents::REFUNDED => ['refund_amount' => $this->container->make(OrderValues::class)->format($order->refunded_total, $order->currency)],
            OrderEvents::PAYMENT_REQUESTED => ['amount_due' => $this->container->make(OrderValues::class)->balance(Balance::of($order))['amount_due']],
            default => [],
        };
    }

    /**
     * @return array{id: int|null, channel: string, provider: string, recipient: string, status: string}
     */
    private static function row(Delivery $row): array
    {
        return [
            'id' => $row->exists ? (int) $row->id : null,
            'channel' => $row->channel_name,
            'provider' => $row->provider,
            'recipient' => $row->recipient,
            'status' => $row->status,
        ];
    }

    private function notifications(): Notifications
    {
        return $this->container->make(Notifications::class);
    }

    /**
     * The channels named, for the screens and the agent: what is chosen and whether it sends.
     *
     * @param  list<int>  $ids
     * @return list<array{id: int, name: string|null, provider: string|null, enabled: bool, deleted: bool}>
     */
    public function describe(array $ids): array
    {
        if ($ids === [] || ! $this->available()) {
            return [];
        }

        $channels = Channel::query()->withTrashed()->whereKey($ids)->get()->keyBy('id');

        return array_map(static function (int $id) use ($channels): array {
            $channel = $channels->get($id);

            return [
                'id' => $id,
                'name' => $channel?->name,
                'provider' => $channel?->provider,
                'enabled' => (bool) $channel?->enabled,
                'deleted' => $channel === null || $channel->trashed(),
            ];
        }, $ids);
    }
}
