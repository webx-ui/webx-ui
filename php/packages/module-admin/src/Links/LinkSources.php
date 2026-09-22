<?php

declare(strict_types=1);

namespace WebxUi\Admin\Links;

use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Contracts\LinkSource;

/**
 * What this panel can link to, filled from service providers.
 *
 * A singleton by the same pattern as `SeoSources` and `RouteTypes`: modules register on boot,
 * everything reads on use, so the order providers happen to boot in never matters. Sorting on
 * read rather than on write, for the same reason — a source registered by a project after the
 * modules still lands where it asked to.
 */
final class LinkSources
{
    /** @var array<string, LinkSource> */
    private array $sources = [];

    public function register(LinkSource $source): void
    {
        $this->sources[$source->type()] = $source;
    }

    /**
     * Every source, in the order a picker draws them.
     *
     * @return list<LinkSource>
     */
    public function all(): array
    {
        $sources = array_values($this->sources);

        usort($sources, static fn (LinkSource $a, LinkSource $b): int => $a->order() <=> $b->order());

        return $sources;
    }

    public function find(string $type): ?LinkSource
    {
        return $this->sources[$type] ?? null;
    }

    /**
     * The sections this administrator may open.
     *
     * The rule lives here rather than in the controller because it is asked twice — once to draw
     * the picker and once to answer a search for a type that was never on offer. A super
     * administrator carries no permissions and passes everything, which is how the panel answers
     * the question everywhere else.
     *
     * @return list<LinkSource>
     */
    public function allowed(?object $user): array
    {
        return array_values(array_filter(
            $this->all(),
            static function (LinkSource $source) use ($user): bool {
                $permission = $source->permission();

                if ($permission === null) {
                    return true;
                }

                return $user instanceof HasPermissions && $user->hasPermission($permission);
            },
        ));
    }

    /** The source behind a type, if this administrator is allowed that section at all. */
    public function allowedSource(string $type, ?object $user): ?LinkSource
    {
        foreach ($this->allowed($user) as $source) {
            if ($source->type() === $type) {
                return $source;
            }
        }

        return null;
    }

    public function forget(): void
    {
        $this->sources = [];
    }
}
