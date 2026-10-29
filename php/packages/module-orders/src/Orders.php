<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Cart\Totals\ResolvedLine;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Orders\Blocks\BlockRefused;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Events\OrderPlaced;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\OrderBenefit;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Models\StatusChange;

/**
 * The one door to an order (spec §5.3, decision 18). The panel, the agent's tools, the checkout
 * and the site's API check the right, read the input, call one method and give back its answer;
 * none of them writes the tables of an order, and neither does a listener of a neighbour.
 *
 * This stage has the order come to be (§6); its milestones, statuses, cancellation and refunds
 * come with the next ones.
 */
final class Orders
{
    public function __construct(
        private readonly Calculator $calculator,
        private readonly CartService $carts,
        private readonly CurrencySource $currencies,
        private readonly OrderBlocks $blocks,
        private readonly OrderNumbers $numbers,
        private readonly InitialStatuses $statuses,
        private readonly ConnectionInterface $db,
        private readonly Dispatcher $events,
        private readonly Validation $validation,
        private readonly Auth $auth,
        private readonly Repository $config,
    ) {}

    /**
     * §6 — the only way an order comes to be: the cart counted again under its lock, checked,
     * frozen into the order, and emptied — all in one transaction, or nothing.
     *
     * A repeat with the same idempotency key is the order it made, and nothing is done again.
     *
     * @throws ValidationException the contacts or the comment
     * @throws BlockRefused a block no module registered, or one its owner refuses
     * @throws CartNotOrderable empty, another currency, lines that cannot be bought
     * @throws PayableChanged the buyer saw another amount
     * @throws OrderRefused a listener of OrderPlacing would not have it
     */
    public function place(PlaceOrder $input): Order
    {
        $customer = $input->customer->normalised();
        $recipient = $input->recipient?->normalised();
        $comment = $input->comment !== null && trim($input->comment) !== '' ? trim($input->comment) : null;

        $this->validate($customer, $recipient, $comment);
        $this->blocks->check($input->blocks);

        $key = $input->idempotencyKey !== null && $input->idempotencyKey !== '' ? self::hash($input->idempotencyKey) : null;

        if ($key !== null && ($order = $this->byHash($key)) !== null) {
            return $order;
        }

        try {
            return $this->db->transaction(fn (): Order => $this->write($input, $customer, $recipient, $comment, $key));
        } catch (UniqueConstraintViolationException $e) {
            // A twin that came past the lock of another cart with the same key: the key is the
            // order's, whichever request made it.
            if ($key !== null && ($order = $this->byHash($key)) !== null) {
                return $order;
            }

            throw $e;
        }
    }

    /** The order a checkout's form token made (§6.2, step 1), or null; the key is hashed the same way. */
    public function findByIdempotencyKey(string $key): ?Order
    {
        return $key === '' ? null : $this->byHash(self::hash($key));
    }

    private function write(PlaceOrder $input, Contact $customer, ?Contact $recipient, ?string $comment, ?string $key): Order
    {
        // 2. The lock of the cart's row, the first of the locks (§6.3): the same cart is not
        // ordered twice from two tabs, and a line added meanwhile waits for the order.
        $cart = $this->carts->lock($input->cart);

        // The twin that waited for the lock finds the order the first one made.
        if ($key !== null && ($order = $this->byHash($key)) !== null) {
            return $order;
        }

        // 3–4. Counted for the order: a source that fails fails the order, not a line of it.
        $state = $this->calculator->calculate($cart, $input->locale, Purpose::Order);
        $this->orderable($cart, $state);

        if ($input->expectedPayable !== null && $state->payable !== $input->expectedPayable) {
            throw new PayableChanged($state, $input->expectedPayable);
        }

        $status = $this->statuses->for($input->awaitPayment);
        $at = CarbonImmutable::now();
        $adminId = $this->adminId($input->source);

        // 6. The order, then its number from its id (§4).
        $order = Order::query()->create([
            'number' => null,
            'idempotency_key' => $key,
            'user_id' => $input->userId,
            'cart_id' => $cart->id,
            'locale' => $input->locale,
            'currency' => (string) $state->currency,
            'status_id' => $status->id,
            'subtotal' => $state->subtotal,
            'adjustments_total' => array_sum(array_map(static fn (Adjustment $adjustment): int => $adjustment->amount, $state->adjustments)),
            'total' => $state->total,
            'tenders_total' => array_sum(array_map(static fn (Tender $tender): int => $tender->amount, $state->tenders)),
            'payable' => $state->payable,
            'refunded_total' => 0,
            'customer_first_name' => $customer->firstName,
            'customer_last_name' => $customer->lastName,
            'customer_phone' => $customer->phone,
            'customer_phone_key' => $customer->phoneKey(),
            'customer_email' => $customer->email,
            'recipient' => $recipient !== null
                ? ['first_name' => $recipient->firstName, 'last_name' => $recipient->lastName, 'phone' => $recipient->phone]
                : null,
            'comment' => $comment,
            'delivery' => $input->blocks['delivery'] ?? null,
            'payment' => $input->blocks['payment'] ?? null,
            'placed_at' => $at,
            'awaiting_payment' => $input->awaitPayment,
        ]);
        $order->number = $this->numbers->format($order->id);
        $order->save();

        $items = $this->writeItems($order, $state);
        $this->writeMoney($order, $state, $items);
        $this->writeHistory($order, $status, $input, $at, $adminId);

        // 7. What must happen with the order or not at all; a refusal rolls all of it back.
        $this->events->dispatch(new OrderPlacing($order, $state, $input->source, $adminId, $at));

        // 8. The lines go; the cart, its cookie and the checkout's draft stay (decision 34).
        $this->carts->empty($cart);

        // After the commit — the dispatcher holds it till then.
        $this->events->dispatch(new OrderPlaced(OrderData::of($order), $input->awaitPayment, $input->source, $adminId, $at));

        return $order;
    }

    /**
     * Lines are never dropped quietly: an empty cart, another currency, or a line that cannot be
     * bought is a refusal the checkout shows the buyer.
     *
     * @throws CartNotOrderable
     */
    private function orderable(Cart $cart, CartState $state): void
    {
        $stored = array_filter($state->lines, static fn (ResolvedLine $line): bool => ! $line->generated());

        if ($stored === []) {
            throw new CartNotOrderable(CartNotOrderable::EMPTY, $state);
        }

        $currency = $this->currencies->currency();

        if ($currency === null || $cart->currency !== $currency) {
            throw new CartNotOrderable(CartNotOrderable::CURRENCY, $state);
        }

        $blocking = array_values(array_filter(
            $state->warnings,
            static fn ($warning): bool => $warning->line !== null && in_array($warning->code, CartNotOrderable::BLOCKING, true),
        ));

        if ($blocking !== []) {
            throw new CartNotOrderable(CartNotOrderable::LINES, $state, $blocking);
        }
    }

    /**
     * The lines, a query a level: the roots, their ids by position, then the children under them.
     * A child's qty is the absolute one the chain counted.
     *
     * @return array<string, int> the order line's id by the cart line's key
     */
    private function writeItems(Order $order, CartState $state): array
    {
        $positions = [];

        foreach ($state->lines as $position => $line) {
            $positions[(string) $line->key] = $position;
        }

        $ids = [];

        foreach ([false, true] as $children) {
            $rows = [];

            foreach ($state->lines as $position => $line) {
                if (($line->parentId !== null) !== $children) {
                    continue;
                }

                $rows[] = $this->itemRow($order, $state, $line, $position, $children ? $ids[(string) $line->parentId] ?? null : null);
            }

            if ($rows === []) {
                continue;
            }

            OrderItem::query()->insert($rows);

            foreach (OrderItem::query()->where('order_id', $order->id)->pluck('id', 'position') as $position => $id) {
                $ids[(string) $state->lines[(int) $position]->key] = (int) $id;
            }
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    private function itemRow(Order $order, CartState $state, ResolvedLine $line, int $position, ?int $parentId): array
    {
        $item = $line->line;
        $price = $line->price ?? 0;
        $adjustments = array_sum(array_map(
            static fn (Adjustment $adjustment): int => $adjustment->amount,
            $state->lineAdjustments[(string) $line->key] ?? [],
        ));
        $names = array_filter($item->names ?? [], static fn (mixed $name): bool => is_string($name) && $name !== '');

        return [
            'order_id' => $order->id,
            'parent_id' => $parentId,
            'position' => $position,
            'type' => $line->type,
            'ref_id' => $line->refId,
            'options' => $line->options !== null ? json_encode($line->options, JSON_UNESCAPED_UNICODE) : null,
            'sku' => self::cut($item?->sku, 64),
            'name' => json_encode($names !== [] ? $names : [$order->locale => $line->name], JSON_UNESCAPED_UNICODE),
            'options_label' => self::cut($item?->optionsLabel, 255),
            // An address cut short is a broken one: none is better.
            'image' => $item?->image !== null && mb_strlen($item->image) <= 255 ? $item->image : null,
            'source' => self::cut($line->source, 64),
            'label' => self::cut($line->label, 64),
            'price' => $price,
            'qty' => $line->effectiveQty,
            'adjustments_total' => $adjustments,
            'total' => $price * $line->effectiveQty + $adjustments,
            'shippable' => $item->shippable ?? true,
        ];
    }

    /**
     * Adjustments, tenders and benefits: a query a table, labels as the chain gave them in the
     * order's language — a rule renamed later does not rename the order's discount.
     *
     * @param  array<string, int>  $items
     */
    private function writeMoney(Order $order, CartState $state, array $items): void
    {
        $adjustments = array_map(static fn (Adjustment $adjustment): array => [
            'order_id' => $order->id,
            'item_id' => $adjustment->line !== null ? $items[(string) $adjustment->line] ?? null : null,
            'source' => (string) self::cut($adjustment->source, 64),
            'label' => (string) self::cut($adjustment->label, 190),
            'amount' => $adjustment->amount,
            'meta' => $adjustment->meta !== [] ? json_encode($adjustment->meta, JSON_UNESCAPED_UNICODE) : null,
        ], $state->adjustments);

        $tenders = array_map(static fn (Tender $tender): array => [
            'order_id' => $order->id,
            'source' => (string) self::cut($tender->source, 64),
            'label' => (string) self::cut($tender->label, 190),
            'amount' => $tender->amount,
            'meta' => $tender->meta !== [] ? json_encode($tender->meta, JSON_UNESCAPED_UNICODE) : null,
        ], $state->tenders);

        // One of a kind: two sources that both pay the shipping are one fact for the waybill.
        $benefits = [];

        foreach ($state->benefits as $benefit) {
            $benefits[$benefit->key] ??= [
                'order_id' => $order->id,
                'key' => (string) self::cut($benefit->key, 64),
                'label' => (string) self::cut($benefit->label, 190),
                'source' => (string) self::cut($benefit->source, 64),
            ];
        }

        foreach ([OrderAdjustment::class => $adjustments, OrderTender::class => $tenders, OrderBenefit::class => array_values($benefits)] as $model => $rows) {
            if ($rows !== []) {
                $model::query()->insert($rows);
            }
        }
    }

    /** «Placed», «awaiting payment» when it does, and the first line of the history: none → the initial status. */
    private function writeHistory(Order $order, OrderStatus $status, PlaceOrder $input, CarbonImmutable $at, ?int $adminId): void
    {
        $milestones = [Milestone::Placed];

        if ($input->awaitPayment) {
            $milestones[] = Milestone::AwaitingPayment;
        }

        OrderMilestone::query()->insert(array_map(static fn (Milestone $milestone): array => [
            'order_id' => $order->id,
            'milestone' => $milestone->value,
            'at' => $at,
            'source' => $input->source,
            'admin_id' => $adminId,
            'created_at' => $at,
        ], $milestones));

        StatusChange::query()->insert([
            'order_id' => $order->id,
            'kind' => StatusChange::STATUS,
            'from_status_id' => null,
            'to_status_id' => $status->id,
            'from_public_id' => null,
            'to_public_id' => $status->public_status_id,
            'source' => $input->source,
            'admin_id' => $adminId,
            'created_at' => $at,
        ]);
    }

    /**
     * The order needs a first name and a way to reach the buyer — a phone or an e-mail; what else
     * a form requires is the checkout's. Answered like a form, keyed by field.
     *
     * @throws ValidationException
     */
    private function validate(Contact $customer, ?Contact $recipient, ?string $comment): void
    {
        $phone = ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 ().\-]*$/'];
        $data = [
            'customer' => ['first_name' => $customer->firstName, 'last_name' => $customer->lastName, 'phone' => $customer->phone, 'email' => $customer->email],
            'comment' => $comment,
        ];
        $rules = [
            'customer.first_name' => ['required', 'string', 'max:190'],
            'customer.last_name' => ['nullable', 'string', 'max:190'],
            'customer.phone' => [...$phone, 'required_without:customer.email'],
            'customer.email' => ['nullable', 'string', 'max:190', 'email:filter', 'required_without:customer.phone'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ];

        if ($recipient !== null) {
            $data['recipient'] = ['first_name' => $recipient->firstName, 'last_name' => $recipient->lastName, 'phone' => $recipient->phone];
            $rules += [
                'recipient.first_name' => ['required', 'string', 'max:190'],
                'recipient.last_name' => ['nullable', 'string', 'max:190'],
                'recipient.phone' => $phone,
            ];
        }

        $this->validation->make($data, $rules)->validate();
    }

    private function byHash(string $hash): ?Order
    {
        return Order::query()->where('idempotency_key', $hash)->first();
    }

    /**
     * Who did it, when a person of the panel or their agent did (§5.3); the checkout, an
     * integration and a command are known by the source alone.
     */
    private function adminId(string $source): ?int
    {
        $guard = $this->config->get('webx-auth.guard');

        if (! in_array($source, ['panel', 'mcp'], true) || ! is_string($guard) || $guard === '' || $this->config->get("auth.guards.{$guard}") === null) {
            return null;
        }

        $id = $this->auth->guard($guard)->id();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    private static function cut(?string $value, int $length): ?string
    {
        return $value !== null ? mb_substr($value, 0, $length) : null;
    }
}
