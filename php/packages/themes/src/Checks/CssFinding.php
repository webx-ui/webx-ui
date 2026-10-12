<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

/** One place a stylesheet breaks §8: what, on which line, and the text that does it. */
final readonly class CssFinding
{
    public function __construct(
        public CssProblem $problem,
        public int $line,
        public string $subject,
    ) {}

    /** `literal colour #fff — no preset … repaints it; …` */
    public function message(): string
    {
        return "{$this->problem->what()} {$this->subject} — {$this->problem->why()}";
    }
}
