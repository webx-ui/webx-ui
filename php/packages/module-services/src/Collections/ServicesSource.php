<?php

declare(strict_types=1);

namespace WebxUi\Services\Collections;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Services\Rendering\ServiceQuery;

/**
 * The services a `wx-collection` field shows: `{ "props": { "source": "services" } }`.
 *
 * A thin layer over {@see ServiceQuery}, so an element here is the same card `services()` gives a
 * template, and who is shown is the same rule. No markup of its own: a list of services says
 * nothing schema.org would take that each service's own page does not already say.
 */
final class ServicesSource implements CollectionSource
{
    public const KEY = 'services';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-services::services.title');
    }

    public function categories(): string
    {
        return 'services/categories';
    }

    public function supportsMarkup(): bool
    {
        return false;
    }

    public function permission(): string
    {
        return 'services.view';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(Selection $selection, string $locale): array
    {
        return (new ServiceQuery)
            ->in($selection->categories)
            ->take($selection->limit)
            ->locale($locale)
            ->get();
    }
}
