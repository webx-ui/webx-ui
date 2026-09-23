<?php

declare(strict_types=1);

namespace WebxUi\Admin\Links;

/**
 * One thing that can be linked to, as a picker shows it and as a render reads it.
 *
 * `available` is deliberately apart from `url`. A menu is built before the pages in it are
 * published, so the picker has to offer a draft — marked, but offered — and the panel draws it
 * dimmed while the site leaves the item out. An address and permission to show it are two
 * different facts, and only the module that owns the entity knows the second one.
 */
final readonly class LinkCandidate
{
    /**
     * @param  int  $id  The entity's key.
     * @param  string  $title  What the editor calls it.
     * @param  string|null  $url  The public address in this language, or null when it has none.
     * @param  bool  $available  Whether the site would show it right now.
     * @param  string|null  $hint  The line under the title: a path in the tree, a rubric, a date.
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $url = null,
        public bool $available = false,
        public ?string $hint = null,
    ) {}

    /**
     * @return array{id: int, title: string, url: string|null, available: bool, hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'available' => $this->available,
            'hint' => $this->hint,
        ];
    }
}
