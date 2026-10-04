<?php

declare(strict_types=1);

namespace WebxUi\Admin\Agents;

/**
 * One installed `webx-ui/*` package, as the site's root AGENTS.md lists it.
 */
final readonly class AgentDoc
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $documented,
    ) {}

    /**
     * Where the package's own guide is, relative to the site root.
     *
     * Into `vendor` on purpose: the text an agent reads is the one that matches the version
     * installed here, not whatever the repository says today.
     */
    public function link(): string
    {
        return "vendor/{$this->name}/".AgentDocs::FILE;
    }
}
