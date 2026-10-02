<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Panel;

use Illuminate\Container\Container;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\CatalogLandings\Demo\LandingsDemo;

/**
 * Landings as an entry of the «Catalog» group, after «Products» and «Brands» (§8.1 of the landings
 * spec): pages of the storefront of their own, not a reference list, so above the «Dictionaries»
 * caption.
 *
 * No permissions of its own (decision 13): the catalogue's `catalog.view` reads, `catalog.manage`
 * writes. The agent's tools are served under the catalogue, as the properties' are (L4).
 */
final class LandingsModule extends AbstractModule implements ProvidesDemo
{
    /** The satellites whose facets the demo's sets are made of. */
    private const SET_SOURCES = ['catalog-brands', 'catalog-labels', 'catalog-properties'];

    public function __construct(private readonly LandingsDemo $demo) {}

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

    /**
     * @return list<string>
     */
    public function requires(): array
    {
        $registry = Container::getInstance()->make(ModuleRegistry::class);

        // Asked for only when installed: a requirement that is not there would skip the whole demo,
        // and a landing on a price needs nothing but the catalogue.
        return ['catalog', ...array_values(array_filter(self::SET_SOURCES, $registry->has(...)))];
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }
}
