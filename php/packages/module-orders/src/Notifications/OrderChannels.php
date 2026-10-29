<?php

declare(strict_types=1);

namespace WebxUi\Orders\Notifications;

use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\Mail\MailRoutes;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\PublicStatus;
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
     * @param  array<string, string>  $extra
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
     *
     * @return list<array<string, mixed>>
     */
    public function deliveries(Order $order): array
    {
        if (! $this->available() || ! $order->exists) {
            return [];
        }

        return $this->container->make(Notifications::class)->deliveriesFor($order)
            ->where('is_test', false)
            ->limit(100)
            ->get()
            ->map(static fn (Delivery $row): array => [
                'id' => (int) $row->id,
                'event' => $row->event,
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
            ])
            ->values()
            ->all();
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
