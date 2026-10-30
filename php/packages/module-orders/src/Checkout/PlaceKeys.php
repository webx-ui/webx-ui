<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Illuminate\Contracts\Session\Session;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;

/**
 * The key of a repeat (spec §24.3): 32 random bytes in hex, printed with the column of the totals
 * and sent back by the confirmation; the order is placed with it, so the same key twice is the
 * same order. The draft keeps its sha256 — what the confirmation is checked against.
 *
 * The key itself waits in the session until an order is made: a read of the page writes no draft
 * (§21.1), and the column printed again — another tab, a step saved — must print the same key,
 * not a new one that turns the page already open stale.
 */
final readonly class PlaceKeys
{
    public const string SESSION = 'webx-orders.checkout.key';

    public function __construct(private Session $session) {}

    /** The key of the column printed now; the draft, when there is one, learns its hash. */
    public function issue(?CheckoutDraft $draft): string
    {
        $key = $this->session->get(self::SESSION);

        // A draft that knows another key had it from another session: that one is the page in
        // flight, and this one starts its own.
        if (! is_string($key) || ! self::wellFormed($key) || ($draft?->place_key !== null && ! hash_equals($draft->place_key, self::hash($key)))) {
            $key = bin2hex(random_bytes(32));
            $this->session->put(self::SESSION, $key);
        }

        if ($draft !== null) {
            $draft->place_key = self::hash($key);
        }

        return $key;
    }

    /**
     * Whether the confirmation comes from the page the draft knows. A draft without a key has
     * no page in flight to be stale against — the first confirmation binds it.
     */
    public function current(?CheckoutDraft $draft, string $key): bool
    {
        if (! self::wellFormed($key)) {
            return false;
        }

        return $draft?->place_key === null || hash_equals($draft->place_key, self::hash($key));
    }

    /** The order is made: the next column prints a new key (§24.3). */
    public function forget(): void
    {
        $this->session->forget(self::SESSION);
    }

    public static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    public static function wellFormed(string $key): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $key) === 1;
    }
}
