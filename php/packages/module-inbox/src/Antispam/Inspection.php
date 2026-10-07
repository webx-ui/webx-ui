<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Antispam;

/**
 * What the guard decided, and which of its layers decided it.
 *
 * The reason is for the log, and for the one refusal that may name itself: a missing or failed
 * captcha, where telling the visitor what to do gives a robot nothing it cannot see on the page
 * anyway. Every other reason is answered with the same sentence, on purpose (§7).
 *
 * `details` is what the provider said — its `error-codes`, a v3 score and action — and never
 * the token or the secret.
 */
final class Inspection
{
    public const HONEYPOT = 'honeypot';

    public const TOO_FAST = 'too_fast';

    public const ORIGIN = 'origin';

    public const CAPTCHA_MISSING = 'captcha_missing';

    public const CAPTCHA_FAILED = 'captcha_failed';

    /** A form asking for a captcha the site has no secret for: nothing the visitor can fix. */
    public const CAPTCHA_UNCONFIGURED = 'captcha_unconfigured';

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly Verdict $verdict,
        public readonly ?string $reason = null,
        public readonly array $details = [],
    ) {}

    public static function pass(): self
    {
        return new self(Verdict::Pass);
    }

    public static function trap(string $reason): self
    {
        return new self(Verdict::Trap, $reason);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function reject(string $reason, array $details = []): self
    {
        return new self(Verdict::Reject, $reason, $details);
    }
}
