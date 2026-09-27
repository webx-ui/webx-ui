<?php

declare(strict_types=1);

namespace WebxUi\Team\Collections;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Team\Rendering\TeamQuery;

/**
 * The people a `wx-collection` field shows: `{ "props": { "source": "team" } }` (§5.3).
 *
 * A thin layer over {@see TeamQuery}, so an element here is the same card `team()` gives a
 * template, and who is shown is the same rule. No categories (decision 1), so the field hides
 * that choice by itself; related to services, so "who provides this service" is a block on the
 * service's page with "only related to — the current one". No markup (decision 2): `Person`
 * without a page of the person has nothing to hang on.
 */
final class TeamSource implements CollectionSource
{
    public const KEY = 'team';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-team::module.team');
    }

    public function categories(): ?string
    {
        return null;
    }

    /**
     * The collections controller drops a target nobody registered, so this is `service` whether or
     * not the site has services.
     *
     * @return list<string>
     */
    public function relations(): array
    {
        return ['service'];
    }

    public function supportsMarkup(): bool
    {
        return false;
    }

    public function permission(): string
    {
        return 'team.view';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(Selection $selection, string $locale): array
    {
        $query = (new TeamQuery)
            ->take($selection->limit)
            ->locale($locale);

        $related = $selection->related();

        if ($related !== null) {
            $query = $query->relatedTo($related['type'], $related['ids']);
        }

        return $query->get();
    }
}
