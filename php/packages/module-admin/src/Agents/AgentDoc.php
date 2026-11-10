<?php

declare(strict_types=1);

namespace WebxUi\Admin\Agents;

/**
 * One installed package of ours, as the site's root AGENTS.md lists it: a `webx-ui/*` package in
 * `vendor`, or a module of the site itself in `modules/` (local).
 */
final readonly class AgentDoc
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $documented,
        public bool $local = false,
        public ?string $directory = null,
    ) {}

    /**
     * Where the package's own guide is, relative to the site root.
     *
     * Into `vendor` on purpose: the text an agent reads is the one that matches the version
     * installed here, not whatever the repository says today. A site module is the site's own
     * code, so its guide is the one in its folder.
     */
    public function link(): string
    {
        if ($this->directory !== null) {
            return $this->directory.'/'.AgentDocs::FILE;
        }

        return "vendor/{$this->name}/".AgentDocs::FILE;
    }
}
