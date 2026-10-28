<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

use WebxUi\Cart\Money;

/**
 * The counted cart: what the API answers, what `cart()->state()` gives a template, what the
 * checkout and the order read later. Amounts are integers in minor units; `toArray()` puts a
 * `*_formatted` string from {@see Money::format()} beside every one of them.
 */
final class CartState
{
    /** The fields of a line in toArray(), for a catalogue that describes the answer; a test holds them to it. */
    public const array LINE_FIELDS = [
        'id', 'type', 'ref', 'source', 'qty', 'effective_qty', 'name', 'sku', 'options', 'options_label', 'image', 'url',
        'price', 'price_formatted', 'price_at_add', 'price_at_add_formatted', 'price_change', 'line_total',
        'line_total_formatted', 'shippable', 'max_qty', 'available', 'gone', 'declinable', 'label', 'adjustments', 'children',
    ];

    /**
     * @param  list<ResolvedLine>  $lines  stored lines in id order, then the generated ones
     * @param  array<string, list<Adjustment>>  $lineAdjustments  by the line's key
     * @param  list<Adjustment>  $adjustments
     * @param  list<Tender>  $tenders
     * @param  list<Benefit>  $benefits
     * @param  list<Hint>  $hints
     * @param  list<Warning>  $warnings
     */
    public function __construct(
        public readonly ?string $currency,
        public readonly string $locale,
        public readonly array $lines,
        public readonly array $lineAdjustments,
        public readonly int $subtotal,
        public readonly array $adjustments,
        public readonly int $total,
        public readonly array $tenders,
        public readonly int $payable,
        public readonly array $benefits,
        public readonly array $hints,
        public readonly array $warnings,
        private readonly Money $money,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /** Units of the stored lines, the unavailable included — what the counter in the header shows. */
    public function units(): int
    {
        $units = 0;

        foreach ($this->lines as $line) {
            if (! $line->generated()) {
                $units += $line->effectiveQty;
            }
        }

        return $units;
    }

    public function hasBenefit(string $key): bool
    {
        foreach ($this->benefits as $benefit) {
            if ($benefit->key === $key) {
                return true;
            }
        }

        return false;
    }

    public function exponent(): int
    {
        return $this->currency === null ? 2 : $this->money->exponent($this->currency);
    }

    /** @return array<string, mixed> the answer of the API */
    public function toArray(): array
    {
        $roots = [];
        $children = [];

        foreach ($this->lines as $line) {
            if ($line->parentId === null) {
                $roots[] = $line;
            } else {
                $children[$line->parentId][] = $line;
            }
        }

        $lines = [];

        foreach ($roots as $root) {
            $row = $this->line($root);
            $row['children'] = is_int($root->key)
                ? array_map($this->line(...), $children[$root->key] ?? [])
                : [];
            $lines[] = $row;
        }

        return [
            'currency' => $this->currency,
            'exponent' => $this->exponent(),
            'locale' => $this->locale,
            'lines' => $lines,
            'units' => $this->units(),
            ...$this->amount('subtotal', $this->subtotal),
            'adjustments' => array_map($this->adjustment(...), $this->adjustments),
            ...$this->amount('total', $this->total),
            'tenders' => array_map(fn (Tender $tender): array => [
                'source' => $tender->source,
                'label' => $tender->label,
                ...$this->amount('amount', $tender->amount),
            ], $this->tenders),
            ...$this->amount('payable', $this->payable),
            'benefits' => array_map(static fn (Benefit $benefit): array => [
                'key' => $benefit->key,
                'label' => $benefit->label,
                'source' => $benefit->source,
            ], $this->benefits),
            'hints' => array_map(fn (Hint $hint): array => [
                'source' => $hint->source,
                'text' => $hint->text,
                ...$this->amount('missing', $hint->missing),
            ], $this->hints),
            'warnings' => array_map(static fn (Warning $warning): array => $warning->toArray(), $this->warnings),
        ];
    }

    /** @return array<string, mixed> */
    private function line(ResolvedLine $line): array
    {
        $item = $line->line;
        $gone = $item === null;
        $adjustments = $this->lineAdjustments[(string) $line->key] ?? [];
        $total = $line->amount();

        if ($line->available) {
            foreach ($adjustments as $adjustment) {
                $total += $adjustment->amount;
            }
        }

        $change = null;

        if (! $gone && $line->price !== null && $line->priceAtAdd !== null && $line->price !== $line->priceAtAdd) {
            $change = $line->price < $line->priceAtAdd ? 'down' : 'up';
        }

        return [
            'id' => $line->key,
            'type' => $line->type,
            'ref' => $line->refId,
            'source' => $line->source,
            'qty' => $line->qty,
            'effective_qty' => $line->effectiveQty,
            'name' => $line->name,
            'sku' => $item?->sku,
            'options' => $line->options === [] ? null : $line->options,
            'options_label' => $item?->optionsLabel,
            'image' => $item?->image,
            'url' => $item?->url,
            ...$this->amount('price', $line->price),
            ...$this->amount('price_at_add', $line->priceAtAdd),
            'price_change' => $change,
            ...$this->amount('line_total', $total),
            'shippable' => $item->shippable ?? true,
            'max_qty' => $item?->maxQty,
            'available' => $line->available,
            'gone' => $gone,
            'declinable' => $line->declinable,
            'label' => $line->label,
            'adjustments' => array_map($this->adjustment(...), $adjustments),
            'children' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function adjustment(Adjustment $adjustment): array
    {
        return [
            'source' => $adjustment->source,
            'label' => $adjustment->label,
            ...$this->amount('amount', $adjustment->amount),
            'line' => $adjustment->line,
        ];
    }

    /** @return array<string, int|string|null> the amount and its string; null stays null in both */
    private function amount(string $name, ?int $minor): array
    {
        return [
            $name => $minor,
            $name.'_formatted' => $minor === null ? null : $this->formatted($minor),
        ];
    }

    private function formatted(int $minor): string
    {
        // No currency configured: a number still reads as one.
        return $this->currency === null ? (string) $minor : $this->money->format($minor, $this->currency);
    }
}
