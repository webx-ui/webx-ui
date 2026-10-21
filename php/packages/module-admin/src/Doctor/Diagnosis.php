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
 *
 * A note is a warning about nothing that is wrong: a state the site is in on purpose, said so a
 * person knows — snapshots left after a transfer, rows kept for a module that may come back. The
 * site works exactly as it would without it, so `--strict` lets it through as well. The rule for
 * a new line: if a deploy should stop until somebody acts, it is a warning; if not, a note.
 *
 * `level()` names all five apart, for a platform that decides by them; `state` stays `warn` for
 * the three kinds of warning, so a reader that knows only ok / warn / fail reads them as before.
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
        public bool $note = false,
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

    /**
     * Something worth knowing that is neither broken nor waiting for anybody: shown, and never a
     * reason to stop a deploy, not even a strict one.
     */
    public static function note(string $subject, string $detail): self
    {
        return new self(self::WARN, $subject, $detail, note: true);
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

    /** Whether `--strict` stops on this line: a failure, or a warning about the setup. */
    public function stops(bool $strict): bool
    {
        return $this->failed() || ($strict && $this->warned() && ! $this->content && ! $this->note);
    }

    /** @return 'ok'|'note'|'content'|'warn'|'fail' */
    public function level(): string
    {
        return match (true) {
            $this->note => 'note',
            $this->content => 'content',
            default => $this->state,
        };
    }
}
