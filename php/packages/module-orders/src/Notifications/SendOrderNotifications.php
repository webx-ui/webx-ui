<?php

declare(strict_types=1);

namespace WebxUi\Orders\Notifications;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderItemsChanged;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderPaymentFailed;
use WebxUi\Orders\Events\OrderPlaced;
use WebxUi\Orders\Events\OrderPublicStatusChanged;
use WebxUi\Orders\Events\OrderReceived;
use WebxUi\Orders\Events\OrderRefunded;
use WebxUi\Orders\Models\PublicStatus;

/**
 * The events of an order go to their channels (spec §10.1). Every event of the order is fired
 * after the commit, so a rolled-back move writes nothing to the log; the values are the snapshot
 * the event carries.
 *
 * `OrderStatusChanged` is not listened to: a move inside a public group tells nobody (decision
 * 12) — the buyer hears of `OrderPublicStatusChanged` alone. Without `webx-ui/module-notifications`
 * every handler is a no-op: the events are still fired for the site's own listeners.
 */
final class SendOrderNotifications
{
    /** Set while the demo makes its orders: made-up orders tell nobody. */
    private static bool $quiet = false;

    public function __construct(private readonly OrderChannels $channels) {}

    /**
     * Run without a notification going out: the demo's twelve orders would otherwise mail the
     * shop's contacts and the made-up buyers the first time anybody looks at the site.
     *
     * @template T
     *
     * @param  Closure(): T  $run
     * @return T
     */
    public static function quietly(Closure $run): mixed
    {
        $was = self::$quiet;
        self::$quiet = true;

        try {
            return $run();
        } finally {
            self::$quiet = $was;
        }
    }

    /** Listened to by class and method, so nothing is built until an order moves. */
    public static function listen(Dispatcher $events): void
    {
        $events->listen(OrderPlaced::class, [self::class, 'placed']);
        $events->listen(OrderPublicStatusChanged::class, [self::class, 'publicStatus']);
        $events->listen(OrderPaid::class, [self::class, 'paid']);
        $events->listen(OrderPaymentFailed::class, [self::class, 'paymentFailed']);
        $events->listen(OrderReceived::class, [self::class, 'received']);
        $events->listen(OrderCancelled::class, [self::class, 'cancelled']);
        $events->listen(OrderRefunded::class, [self::class, 'refunded']);
        $events->listen(OrderItemsChanged::class, [self::class, 'itemsChanged']);
    }

    public function placed(OrderPlaced $event): void
    {
        $this->milestone(OrderEvents::PLACED, $event->order);
    }

    public function publicStatus(OrderPublicStatusChanged $event): void
    {
        if (self::$quiet || ! $this->channels->available()) {
            return;
        }

        // The group's channels as they are now; a group deleted since the move sends nothing (§5.6).
        $ids = OrderChannels::ofPublic(PublicStatus::withTrashed()->find($event->to->id));

        $this->channels->send(OrderEvents::PUBLIC_STATUS, $ids, $event->order);
    }

    public function paid(OrderPaid $event): void
    {
        $this->milestone(OrderEvents::PAID, $event->order, ['reference' => (string) $event->reference]);
    }

    public function paymentFailed(OrderPaymentFailed $event): void
    {
        $this->milestone(OrderEvents::PAYMENT_FAILED, $event->order, ['reason' => (string) $event->reason]);
    }

    public function received(OrderReceived $event): void
    {
        $this->milestone(OrderEvents::RECEIVED, $event->order, ['reference' => (string) $event->reference]);
    }

    public function cancelled(OrderCancelled $event): void
    {
        $this->milestone(OrderEvents::CANCELLED, $event->order, ['reason' => (string) $event->reason]);
    }

    public function refunded(OrderRefunded $event): void
    {
        $values = $this->channels->available()
            ? ['refund_amount' => app(OrderValues::class)->format($event->refund->amount, $event->order->currency)]
            : [];

        $this->milestone(OrderEvents::REFUNDED, $event->order, $values);
    }

    /**
     * The lines were edited (§40.2): the buyer hears of it only when the manager left «Tell the
     * buyer» on — the dialog's flag, the agent's `notify`.
     */
    public function itemsChanged(OrderItemsChanged $event): void
    {
        if (! $event->notify || self::$quiet || ! $this->channels->available()) {
            return;
        }

        $this->milestone(OrderEvents::ITEMS_CHANGED, $event->order, app(OrderValues::class)->edit($event->revision, $event->balance, $event->order->locale));
    }

    /**
     * @param  array<string, string|list<array<string, string>>>  $extra
     */
    private function milestone(string $key, OrderData $order, array $extra = []): void
    {
        if (self::$quiet || ! $this->channels->available()) {
            return;
        }

        $this->channels->send($key, $this->channels->forEvent($key), $order, $extra);
    }
}
