<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor;

/**
 * One line of the report: what was looked at, and how it went.
 *
 * A failure carries what to do about it in the same sentence, because the person reading this
 * is usually deploying and has never seen the code. "Storage: failed" sends them looking; "run
 * php artisan storage:link" does not.
 */
final readonly class Diagnosis
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    private function __construct(
        public string $state,
        public string $subject,
        public string $detail,
    ) {}

    public static function ok(string $subject, string $detail): self
    {
        return new self(self::OK, $subject, $detail);
    }

    /** Something to know about that is not in the way — the deploy carries on. */
    public static function warn(string $subject, string $detail): self
    {
        return new self(self::WARN, $subject, $detail);
    }

    /** Something broken, said together with the one command that mends it. */
    public static function fail(string $subject, string $detail): self
    {
        return new self(self::FAIL, $subject, $detail);
    }

    public function failed(): bool
    {
        return $this->state === self::FAIL;
    }

    public function warned(): bool
    {
        return $this->state === self::WARN;
    }
}
