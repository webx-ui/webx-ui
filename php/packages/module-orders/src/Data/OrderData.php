<?php

declare(strict_types=1);

namespace WebxUi\Orders\Data;

use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\OrderBenefit;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderTender;

/**
 * The order as it was at the moment of an event (§7.1): plain values only, so a listener in a
 * queue gets it serialised and the same — and one that needs the model takes it by `id`. Names
 * are in the order's language.
 */
final readonly class OrderData
{
    /**
     * @param  list<ItemData>  $items
     * @param  list<AdjustmentData>  $adjustments
     * @param  list<TenderData>  $tenders
     * @param  list<string>  $benefits  keys
     * @param  array<string, mixed>|null  $delivery
     * @param  array<string, mixed>|null  $payment
     * @param  array<string, string>  $milestones  milestone → ISO 8601
     */
    public function __construct(
        public int $id,
        public string $number,
        public ?int $userId,
        public string $locale,
        public string $currency,
        public int $subtotal,
        public int $adjustmentsTotal,
        public int $total,
        public int $tendersTotal,
        public int $payable,
        public int $refundedTotal,
        public ContactData $customer,
        public ?ContactData $recipient,
        public ?string $comment,
        public array $items,
        public array $adjustments,
        public array $tenders,
        public array $benefits,
        public ?array $delivery,
        public ?array $payment,
        public StatusData $status,
        public PublicStatusData $publicStatus,
        public array $milestones,
        /** Money that came in (§36.1): the payment and any top-up after an edit. */
        public int $paidTotal = 0,
        /** `owed − paidTotal + refundedTotal`: > 0 the buyer owes, < 0 the shop does. */
        public int $balance = 0,
        /** The edit the lines are at (§37.1); 0 — as placed. */
        public int $itemsRevision = 0,
    ) {}

    /**
     * From the model with its lines, money, milestones and status loaded — or loaded here. A line
     * an edit took out whole is not in it (§39); one with units refunded stays, with `removedAt`.
     */
    public static function of(Order $order): self
    {
        $order->loadMissing(['items', 'adjustments', 'tenders', 'benefits', 'milestones', 'status.publicStatus']);
        $locale = $order->locale;
        $recipient = $order->recipient;
        $milestones = [];

        foreach ($order->milestones as $milestone) {
            /** @var OrderMilestone $milestone */
            $milestones[$milestone->milestone->value] = $milestone->at->toIso8601String();
        }

        return new self(
            id: $order->id,
            number: (string) $order->number,
            userId: $order->user_id,
            locale: $locale,
            currency: $order->currency,
            subtotal: $order->subtotal,
            adjustmentsTotal: $order->adjustments_total,
            total: $order->total,
            tendersTotal: $order->tenders_total,
            payable: $order->payable,
            refundedTotal: $order->refunded_total,
            customer: new ContactData($order->customer_first_name, $order->customer_last_name, $order->customer_phone, $order->customer_email),
            recipient: $recipient !== null ? new ContactData($recipient['first_name'], $recipient['last_name'] ?? null, $recipient['phone'] ?? null, null) : null,
            comment: $order->comment,
            items: array_values($order->items->filter(static fn (OrderItem $item): bool => $item->removed_at === null || $item->qty > 0)->map(static fn (OrderItem $item): ItemData => new ItemData(
                id: $item->id,
                parentId: $item->parent_id,
                type: $item->type,
                refId: $item->ref_id,
                sku: $item->sku,
                name: (string) ($item->name[$locale] ?? reset($item->name) ?: ''),
                optionsLabel: $item->options_label,
                price: $item->price,
                qty: $item->qty,
                total: $item->total,
                shippable: $item->shippable,
                source: $item->source,
                refundedQty: $item->refunded_qty,
                revision: $item->revision,
                removedAt: $item->removed_at?->toIso8601String(),
            ))->all()),
            adjustments: array_values($order->adjustments->map(static fn (OrderAdjustment $adjustment): AdjustmentData => new AdjustmentData(
                id: $adjustment->id,
                itemId: $adjustment->item_id,
                source: $adjustment->source,
                label: $adjustment->label,
                amount: $adjustment->amount,
                meta: $adjustment->meta ?? [],
            ))->all()),
            tenders: array_values($order->tenders->map(static fn (OrderTender $tender): TenderData => new TenderData(
                id: $tender->id,
                source: $tender->source,
                label: $tender->label,
                amount: $tender->amount,
                refundedAmount: $tender->refunded_amount,
                meta: $tender->meta ?? [],
            ))->all()),
            benefits: array_values($order->benefits->map(static fn (OrderBenefit $benefit): string => $benefit->key)->all()),
            delivery: $order->delivery,
            payment: $order->payment,
            status: StatusData::of($order->status, $locale),
            publicStatus: PublicStatusData::of($order->status->publicStatus, $locale),
            milestones: $milestones,
            paidTotal: $order->paid_total,
            balance: $order->balance,
            itemsRevision: $order->items_revision,
        );
    }
}
