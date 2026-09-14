<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

/**
 * The sources, in the order they are asked.
 *
 * A singleton filled from service providers. Sorting happens on read rather than on write so a
 * source registered after the first page was rendered still lands in the right place.
 */
final class SeoSources
{
    /** @var list<SeoSource> */
    private array $sources = [];

    public function register(SeoSource $source): void
    {
        $this->sources[] = $source;
    }

    /**
     * Highest priority first. Equal priorities keep registration order, so a project that
     * deliberately sits alongside a built-in source stays where it put itself.
     *
     * @return list<SeoSource>
     */
    public function all(): array
    {
        $sources = $this->sources;

        usort($sources, static fn (SeoSource $a, SeoSource $b): int => $b->priority() <=> $a->priority());

        return $sources;
    }

    public function forget(): void
    {
        $this->sources = [];
    }
}
