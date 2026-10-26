<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Engine;

use WebxUi\Configurator\Pricing\Breakdown;

/**
 * A price and how it was made (§7.4): the area, the subtotal of the sums, the total before and
 * after the discount, and the lines in the order they were counted.
 *
 * The numbers are what the parity cases compare; the words of a line (`line`) are added by
 * {@see Breakdown}, because they belong to a language and the engine
 * has none.
 */
final class Quote
{
    /**
     * @param  list<array{step: string|null, option: string|null, kind: string, amount: float, rate?: float, area?: float}>  $lines
     */
    public function __construct(
        public readonly string $currency,
        public readonly float $area,
        public readonly float $subtotal,
        public readonly float $totalBeforeDiscount,
        public readonly float $total,
        public readonly array $lines,
        public readonly float $round,
        public readonly State $state,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'normalized' => $this->state->normalized(),
            'hidden' => $this->state->hidden,
            'area' => Engine::number($this->area),
            'subtotal' => Engine::number($this->subtotal),
            'total_before_discount' => Engine::number($this->totalBeforeDiscount),
            'total' => Engine::number($this->total),
            'round' => Engine::number($this->round),
            'lines' => array_map(
                static fn (array $line): array => array_map(Engine::number(...), $line),
                $this->lines,
            ),
        ];
    }
}
