<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;

/**
 * A source with its answers written out, so the frame's half can be tested without a content
 * module: what is under test here is the register, the endpoints and the permission filter, and
 * none of those cares where a candidate came from.
 */
final class FakeLinkSource implements LinkSource
{
    /**
     * @param  list<LinkCandidate>  $candidates
     */
    public function __construct(
        private readonly string $type = 'page',
        private readonly array $candidates = [],
        private readonly ?string $permission = null,
        private readonly int $order = 100,
        private readonly string $title = 'Pages',
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function model(): string
    {
        return Ticket::class;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function icon(): string
    {
        return 'file';
    }

    public function order(): int
    {
        return $this->order;
    }

    public function permission(): ?string
    {
        return $this->permission;
    }

    /**
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array
    {
        $found = array_values(array_filter(
            $this->candidates,
            static fn (LinkCandidate $candidate): bool => $query === ''
                || stripos($candidate->title, $query) !== false,
        ));

        return array_slice($found, 0, $limit);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, LinkCandidate>
     */
    public function resolve(array $ids, string $locale): array
    {
        $resolved = [];

        foreach ($this->candidates as $candidate) {
            if (in_array($candidate->id, $ids, true)) {
                $resolved[$candidate->id] = $candidate;
            }
        }

        return $resolved;
    }
}
