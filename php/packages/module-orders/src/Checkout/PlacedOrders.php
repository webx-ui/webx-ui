<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Illuminate\Contracts\Session\Session;

/**
 * The orders this session placed (spec §26): «Thank you» opens without a signature only for them.
 * The last five, each with how much less it came to than the buyer saw (§24.4) — that is told on
 * the page and never written into the order.
 */
final readonly class PlacedOrders
{
    public const string SESSION = 'webx-orders.checkout.placed';

    private const int KEEP = 5;

    public function __construct(private Session $session) {}

    public function remember(string $number, ?int $decrease = null): void
    {
        $placed = array_filter($this->all(), static fn (array $entry): bool => $entry['number'] !== $number);
        $placed[] = ['number' => $number, 'decrease' => $decrease];

        $this->session->put(self::SESSION, array_slice(array_values($placed), -self::KEEP));
    }

    public function has(string $number): bool
    {
        return $this->find($number) !== null;
    }

    /** How much less the order came to, in minor units; null — as much as the buyer saw. */
    public function decrease(string $number): ?int
    {
        return $this->find($number)['decrease'] ?? null;
    }

    /** @return array{number: string, decrease: int|null}|null */
    private function find(string $number): ?array
    {
        foreach ($this->all() as $entry) {
            if ($entry['number'] === $number) {
                return $entry;
            }
        }

        return null;
    }

    /** @return list<array{number: string, decrease: int|null}> */
    private function all(): array
    {
        $stored = $this->session->get(self::SESSION);
        $entries = [];

        foreach (is_array($stored) ? $stored : [] as $entry) {
            if (is_array($entry) && is_string($entry['number'] ?? null)) {
                $decrease = $entry['decrease'] ?? null;
                $entries[] = ['number' => $entry['number'], 'decrease' => is_int($decrease) && $decrease > 0 ? $decrease : null];
            }
        }

        return $entries;
    }
}
