<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks;

/**
 * What a fix would change, shown before it is pressed and answered to `dry_run`: one row per
 * record and field, with the number of replacements in it.
 */
final readonly class FixPreview
{
    /**
     * @param  list<array{label: string, field: string, count: int, edit_url?: string|null}>  $changes
     */
    public function __construct(
        public array $changes = [],
        public ?string $note = null,
    ) {}

    public function total(): int
    {
        return array_sum(array_column($this->changes, 'count'));
    }
}
