<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks;

/**
 * What a fix would change, shown before it is pressed and answered to `dry_run`: one row per
 * thing that changes — a field of a record with the number of replacements in it, or a setting
 * with its value before and after — and a note for what a row cannot say (a web server that
 * answers before Laravel, a cache to clear).
 *
 * An empty preview means there is nothing left to change: the fix is not offered.
 */
final readonly class FixPreview
{
    /**
     * @param  list<array{label: string, field?: string|null, count?: int, before?: string|null, after?: string|null, edit_url?: string|null}>  $changes
     */
    public function __construct(
        public array $changes = [],
        public ?string $note = null,
    ) {}

    /** How many things change: replacements where they are counted, a row otherwise. */
    public function total(): int
    {
        return array_sum(array_map(static fn (array $change): int => $change['count'] ?? 1, $this->changes));
    }

    public function empty(): bool
    {
        return $this->changes === [];
    }

    /**
     * @return array{changes: list<array<string, mixed>>, total: int, note: string|null}
     */
    public function toArray(): array
    {
        return ['changes' => $this->changes, 'total' => $this->total(), 'note' => $this->note];
    }
}
