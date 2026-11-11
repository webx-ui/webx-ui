<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * The sources a `wx-embed` field names in `props.source`, by that name — filled by the packages
 * that print the frames: `google-maps` by `webx-ui/widgets`. The panel knows nothing of them; a
 * node naming a source nobody registered refuses every value rather than keeping HTML unchecked.
 */
final class Embeds
{
    /** @var array<string, EmbedSource> */
    private array $sources = [];

    public function register(string $name, EmbedSource $source): void
    {
        $this->sources[$name] = $source;
    }

    public function has(string $name): bool
    {
        return isset($this->sources[$name]);
    }

    public function get(string $name): ?EmbedSource
    {
        return $this->sources[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->sources);
        sort($names);

        return $names;
    }
}
