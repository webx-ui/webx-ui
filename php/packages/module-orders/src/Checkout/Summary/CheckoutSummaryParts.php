<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Summary;

use Illuminate\Support\Facades\Log;
use Throwable;
use WebxUi\Orders\Checkout\CheckoutContext;

/**
 * The registry of the parts of the column (spec §23.3), empty in the package (decision 51):
 * `module-coupons` and `module-wallet` register theirs in `boot()`. The node
 * `checkout.summary.parts` of the composition prints them; a theme moves it.
 */
final class CheckoutSummaryParts
{
    /** @var array<string, array{part: SummaryPart, position: int}> */
    private array $parts = [];

    public function register(string $key, SummaryPart $part, int $position): void
    {
        $this->parts[$key] = ['part' => $part, 'position' => $position];
    }

    /**
     * Every registered part by key with its class and position, in their order — for `orders://checkout`.
     *
     * @return array<string, array{class: class-string, position: int}>
     */
    public function describe(): array
    {
        $parts = $this->parts;
        uasort($parts, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return array_map(static fn (array $entry): array => ['class' => $entry['part']::class, 'position' => $entry['position']], $parts);
    }

    /**
     * The HTML of the parts that apply, by key in the order of their positions. A part that
     * throws is left out and logged: the column, and the confirmation in it, stays.
     *
     * @return array<string, string>
     */
    public function render(CheckoutContext $context): array
    {
        $parts = $this->parts;
        uasort($parts, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);
        $html = [];

        foreach ($parts as $key => ['part' => $part]) {
            try {
                if ($part->applies($context)) {
                    $html[$key] = $part->render($context)->toHtml();
                }
            } catch (Throwable $e) {
                Log::warning("webx-orders: the checkout's summary part «{$key}» failed", ['exception' => $e]);
            }
        }

        return $html;
    }
}
