<?php

declare(strict_types=1);

namespace WebxUi\Themes\Sync;

use WebxUi\Themes\Contracts\SyncStep;

/** What a {@see SyncStep} did to one thing: `hero` — `created from webx-ui/theme-default`. */
final readonly class SyncLine
{
    public function __construct(
        public string $subject,
        public string $detail,
        public bool $refused = false,
    ) {}

    public static function refused(string $subject, string $why): self
    {
        return new self($subject, $why, true);
    }
}
