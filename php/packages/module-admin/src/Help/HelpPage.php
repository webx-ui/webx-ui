<?php

declare(strict_types=1);

namespace WebxUi\Admin\Help;

/**
 * The parts of one key put together in one language: what the files say, before the tree of the
 * screen gives the fields their labels and tabs.
 */
final readonly class HelpPage
{
    /**
     * @param  string  $locale  The language of the owner's text, which is what a reader sees
     *                          first. A satellite may have fallen back on its own.
     * @param  list<string>  $include
     * @param  list<array{title: string, body: string}>  $howto
     * @param  array<string, string>  $fields  What the files of this key describe.
     * @param  array<string, string>  $shared  What `_fields.md` describes on any screen.
     */
    public function __construct(
        public string $key,
        public string $locale,
        public string $title,
        public string $about,
        public ?string $guide = null,
        public array $include = [],
        public array $howto = [],
        public array $fields = [],
        public array $shared = [],
    ) {}

    /**
     * The description of a node. The file of the screen is stronger than `_fields.md`.
     */
    public function field(string $id): ?string
    {
        return $this->fields[$id] ?? $this->shared[$id] ?? null;
    }
}
