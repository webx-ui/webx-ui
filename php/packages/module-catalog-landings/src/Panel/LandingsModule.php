<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Catalog\Panel\CatalogModule;

/**
 * Landings as an entry of the «Catalog» group, after «Products» and «Brands» (§8.1 of the landings
 * spec): pages of the storefront of their own, not a reference list, so above the «Dictionaries»
 * caption.
 *
 * No permissions of its own (decision 13): the catalogue's `catalog.view` reads, `catalog.manage`
 * writes. The agent's tools are served under the catalogue, as the properties' are (L4).
 */
final class LandingsModule extends AbstractModule
{
    public function id(): string
    {
        return 'catalog-landings';
    }

    public function title(): string
    {
        return (string) __('webx-catalog-landings::module.title');
    }

    public function icon(): string
    {
        return 'filter';
    }

    public function order(): int
    {
        return 303;
    }

    public function group(): string
    {
        return CatalogModule::GROUP;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [];
    }
}
