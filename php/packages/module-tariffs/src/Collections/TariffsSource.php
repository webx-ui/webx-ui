<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Collections;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Tariffs\Rendering\TariffQuery;

/**
 * The tariffs a `wx-collection` field shows: `{ "props": { "source": "tariffs" } }` (§4.2).
 *
 * A thin layer over {@see TariffQuery}, so an element here is the same card `tariffs()` gives a
 * template, and who is shown is the same rule. Picked by group, and related to services, so "what
 * this service costs" is a block on the service's page with "only related to — the current one".
 *
 * No markup (decision 7): an `Offer` needs something it offers and a page of it, and a tariff has
 * neither; on a service's page the `Service` markup is the services module's, and a second one
 * beside it would either repeat or contradict it.
 */
final class TariffsSource implements CollectionSource
{
    public const KEY = 'tariffs';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-tariffs::module.tariffs');
    }

    public function categories(): string
    {
        return 'tariffs/categories';
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
        return 'tariffs.view';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(Selection $selection, string $locale): array
    {
        return (new TariffQuery)->selected($selection)->locale($locale)->get();
    }
}
