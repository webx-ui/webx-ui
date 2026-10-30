<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * What `editItems()` answers (spec §37.1): the lines «before — after», the totals before and
 * after, the balance after, what the manager should know (§37.7) and, on a dry run, a refusal in
 * the answer instead of an exception — the panel's dialog and `orders_items_edit` show the same
 * thing from it.
 */
final readonly class ItemsEditResult
{
    /** Another edit was saved since the manager opened the order. */
    public const string STALE = 'stale';

    /** The edit adds, changes and takes out nothing. */
    public const string NOTHING = 'nothing';

    /** No line would be left: that is cancelling the order, not editing it. */
    public const string EMPTY = 'empty';

    /** Received, cancelled or given back whole (§37.2): a return and a new order instead. */
    public const string STATE = 'state';

    /** A line of a bundle: the bundle changes whole, by its parent. */
    public const string CHILD = 'child';

    /** The qty of a rule's gift: the rule gave so many; the gift may only go. */
    public const string GENERATED = 'generated';

    /** Fewer units than were refunded of the line. */
    public const string BELOW_REFUNDED = 'below_refunded';

    /** The line is not one of the order's — or taken out already. */
    public const string FOREIGN = 'foreign';

    public const array REFUSALS = [self::STALE, self::NOTHING, self::EMPTY, self::STATE, self::CHILD, self::GENERATED, self::BELOW_REFUNDED, self::FOREIGN];

    /** The order was paid: the sum changes, and the balance says what to take or give back. */
    public const string WARN_PAID = 'paid';

    /** A discount on the whole order kept its amount (§37.3): it was counted from the old sum. */
    public const string WARN_ADJUSTMENT_KEPT = 'adjustment_kept';

    /** A benefit stays, though the new sum may be below the rule's threshold. */
    public const string WARN_BENEFIT_KEPT = 'benefit_kept';

    /** A delivery block, and no line left that is delivered. */
    public const string WARN_NOTHING_SHIPPABLE = 'nothing_shippable';

    /** The tenders are more than the order costs now (§37.5): give them back by a refund. */
    public const string WARN_TENDERS_OVER = 'tenders_over';

    /**
     * @param  list<array{op: string, item_id: int|null, name: string, options_label: string|null, price: int, qty_from: int, qty_to: int, total_from: int, total_to: int}>  $changes  top-level lines; `item_id` of a line added is null on a dry run
     * @param  list<array{adjustment_id: int, label: string, amount_from: int, amount_to: int}>  $adjustments  the ones that changed
     * @param  array{subtotal: int, adjustments_total: int, total: int, tenders_total: int, payable: int, balance: int}  $before
     * @param  array{subtotal: int, adjustments_total: int, total: int, tenders_total: int, payable: int, balance: int}  $after
     * @param  list<array<string, mixed>>  $warnings  each {code, …}: the codes above, and the cart's for a line added (`line.qty_limited`, with `line` — `add.N`)
     * @param  string|null  $field  where a refusal points: `qty.<id>`, `remove.<n>`, `revision`
     * @param  int|null  $revision  the number of the edit written; null on a dry run and a refusal
     */
    public function __construct(
        public array $changes,
        public array $adjustments,
        public array $before,
        public array $after,
        public Balance $balance,
        public array $warnings,
        public ?string $refusal,
        public ?string $field,
        public ?int $revision,
        public bool $dryRun,
    ) {}

    /** @return list<array{op: string, item_id: int|null, name: string, options_label: string|null, price: int, qty_from: int, qty_to: int, total_from: int, total_to: int}> */
    public function added(): array
    {
        return $this->of('add');
    }

    /** @return list<array{op: string, item_id: int|null, name: string, options_label: string|null, price: int, qty_from: int, qty_to: int, total_from: int, total_to: int}> */
    public function changed(): array
    {
        return $this->of('qty');
    }

    /** @return list<array{op: string, item_id: int|null, name: string, options_label: string|null, price: int, qty_from: int, qty_to: int, total_from: int, total_to: int}> */
    public function removed(): array
    {
        return $this->of('remove');
    }

    /** @return list<string> */
    public function warningCodes(): array
    {
        return array_values(array_map(static fn (array $warning): string => (string) $warning['code'], $this->warnings));
    }

    /** @return list<array{op: string, item_id: int|null, name: string, options_label: string|null, price: int, qty_from: int, qty_to: int, total_from: int, total_to: int}> */
    private function of(string $op): array
    {
        return array_values(array_filter($this->changes, static fn (array $change): bool => $change['op'] === $op));
    }
}
