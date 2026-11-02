<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Api;

/**
 * The pause between the requests of a sync (§6.6): at most `requests_per_minute`, so that a night
 * pass stays within the account's limit — the carrier refuses two directory requests in one second
 * («To many requests», stage 3). Only the sync waits; the panel's calls do not.
 */
final class Pace
{
    private ?float $last = null;

    public function __construct(private readonly int $perMinute = 60) {}

    /** Waits until the next request may go, and counts it as gone. */
    public function wait(): void
    {
        if ($this->perMinute > 0 && $this->last !== null) {
            $left = $this->last + 60 / $this->perMinute - microtime(true);

            if ($left > 0) {
                usleep((int) ceil($left * 1_000_000));
            }
        }

        $this->last = microtime(true);
    }
}
