<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use WebxUi\Admin\Support\Authors;

/**
 * A row of the journal as the panel and an agent read it: one shape for both, so that what the
 * node shows and what the agent is told cannot drift apart.
 *
 * The labels are the type's, in the reader's language, at the moment of reading — a module that
 * renames "Price" to "Base price" renames it in last year's rows too.
 */
final readonly class HistoryPresenter
{
    public function __construct(private HistoryTypes $types) {}

    /**
     * A page of rows for the panel, with the authors' photographs read in one query for the page.
     *
     * @param  iterable<array-key, HistoryEntry>  $entries
     * @return list<array<string, mixed>>
     */
    public function page(iterable $entries, mixed $reader): array
    {
        $entries = is_array($entries) ? array_values($entries) : array_values(iterator_to_array($entries));
        $people = Authors::people($reader, array_map(static fn (HistoryEntry $entry): ?int => $entry->admin_id, $entries));

        return array_map(fn (HistoryEntry $entry): array => $this->row($entry, $people), $entries);
    }

    /**
     * @param  array<int, array{name: string, avatar: string|null}>  $people  the authors' photographs,
     *                                                                        from {@see self::page()}
     * @return array<string, mixed>
     */
    public function row(HistoryEntry $entry, array $people = []): array
    {
        $type = $this->types->find($entry->subject_type);
        $changes = [];

        foreach ($entry->changes ?? [] as $change) {
            $field = (string) ($change['field'] ?? '');
            $stored = $change['label'] ?? null;

            $changes[] = ['field' => $field, 'label' => $type?->label($field, is_string($stored) ? $stored : null) ?? $field]
                + array_diff_key($change, ['field' => true, 'label' => true]);
        }

        $row = [
            'id' => $entry->id,
            'event' => $entry->event,
            'source' => $entry->source,
            'subject' => ['type' => $entry->subject_type, 'id' => $entry->subject_id],
            // The name as it was: an account deleted since still signed what it did. The photograph
            // is today's, and there is none for an account that is gone — initials, then.
            'admin' => $entry->admin_id === null && $entry->admin_name === ''
                ? null
                : [
                    'id' => $entry->admin_id,
                    'name' => $entry->admin_name === '' ? null : $entry->admin_name,
                    'avatar' => $entry->admin_id === null ? null : ($people[$entry->admin_id]['avatar'] ?? null),
                ],
            'grant_id' => $entry->grant_id,
            'changes' => $changes,
            'run' => $entry->parent_id === null ? null : ['id' => $entry->parent_id, 'summary' => $this->summaryOf($entry)],
            'created_at' => $entry->created_at?->toAtomString(),
        ];

        if ($entry->isRun()) {
            $row['summary'] = $entry->summary ?? [];
        }

        return $row;
    }

    /**
     * What the run a row belongs to was — "import of prices.csv" beside the link — when the
     * reader loaded it; a link without words otherwise.
     *
     * @return array<string, mixed>|null
     */
    private function summaryOf(HistoryEntry $entry): ?array
    {
        if (! $entry->relationLoaded('run')) {
            return null;
        }

        $run = $entry->getRelation('run');

        return $run instanceof HistoryEntry ? ($run->summary ?? []) : null;
    }
}
