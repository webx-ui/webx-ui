<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor;

/**
 * One line of the report: what was looked at, and how it went.
 *
 * A failure carries what to do about it in the same sentence, because the person reading this
 * is usually deploying and has never seen the code. "Storage: failed" sends them looking; "run
 * php artisan storage:link" does not.
 *
 * A warning is either about how the site is built or about what is in it. The second sort — a
 * shared block nobody placed, a property code that clashes with a column — is mended in the panel
 * by an editor, not by whoever deploys, so `--strict` lets it through and says it apart.
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
        public bool $content = false,
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

    /**
     * A warning about the content of the site rather than its setup: mended in the panel, so it
     * never stops a deploy, not even a strict one.
     */
    public static function content(string $subject, string $detail): self
    {
        return new self(self::WARN, $subject, $detail, true);
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
