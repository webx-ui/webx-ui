<?php

declare(strict_types=1);

namespace WebxUi\Admin\Boot;

/**
 * One artisan command a container runs before it starts serving, and why it is in the list.
 *
 * A step is a value rather than a closure so that the whole sequence can be read — and
 * tested — without running any of it: `webx:boot --pretend` prints exactly this.
 */
final class Step
{
    /**
     * @param  string  $what  The step by the name a person would look for in the output.
     * @param  list<string>  $command  What is handed to `artisan`, the command name first.
     * @param  string  $why  What goes wrong on a site where this step never runs.
     * @param  bool  $fatal  Whether a non-zero exit stops the boot. Steps that are already
     *                       done report failure — `webx:admin` on the second boot, for one —
     *                       so the ones that cannot tell the two apart are not fatal.
     * @param  array<string, string>  $env  Passed to the child on top of what it inherits.
     *                                      A secret travels here rather than in an argument:
     *                                      arguments land in `ps` and in shell history.
     */
    public function __construct(
        public readonly string $what,
        public readonly array $command,
        public readonly string $why,
        public readonly bool $fatal = true,
        public readonly array $env = [],
    ) {}

    /** The command as somebody would type it, for the output and for a test to assert on. */
    public function line(): string
    {
        return 'php artisan '.implode(' ', $this->command);
    }
}
