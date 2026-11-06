<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Panel;

use Illuminate\Container\Container;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\CatalogCollections\CatalogCollectionsServiceProvider;
use WebxUi\CatalogCollections\Demo\CollectionsDemo;

/**
 * Collections as an entry of the «Catalog» group, below «Landings» (§6 of the collections spec).
 *
 * No permissions of their own: the catalogue's `catalog.view` reads, `catalog.manage` writes.
 */
final class CollectionsModule extends AbstractModule implements HasVisibility, ProvidesDemo
{
    /** What the demo stands on when the site has it: a label's collection, the home page, an article. */
    private const DEMO_SOURCES = ['catalog-labels', 'pages', 'articles'];

    public function __construct(private readonly CollectionsDemo $demo) {}

    public function id(): string
    {
        return CatalogCollectionsServiceProvider::MODULE;
    }

    public function title(): string
    {
        return (string) __('webx-catalog-collections::module.title');
    }

    public function icon(): string
    {
        return 'grid';
    }

    public function order(): int
    {
        return 305;
    }

    public function group(): string
    {
        return CatalogModule::GROUP;
    }

    /**
     * @return list<string>
     */
    public function visibleWith(): array
    {
        return ['catalog.view', 'catalog.manage'];
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
        // and a collection of new arrivals needs nothing but the catalogue.
        return ['catalog', ...array_values(array_filter(self::DEMO_SOURCES, $registry->has(...)))];
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }
}
