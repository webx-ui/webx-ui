<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\CatalogProperties\Demo\PropertiesDemo;
use WebxUi\CatalogProperties\Mcp\PropertyTools;

/**
 * The properties as an entry of the «Catalog» group, under its «Dictionaries» caption, after the
 * labels and the stock (§7.1 of the properties spec). Their groups are a button in the head of the
 * section rather than an entry of their own: they are opened once, when the card is laid out.
 *
 * No permissions of its own (decision 19): the catalogue's `catalog.view` reads, `catalog.manage`
 * writes. The agent's tools are not this module's: they are served under the catalogue
 * ({@see PropertyTools}), where their names belong.
 */
final class PropertiesModule extends AbstractModule implements HasNavSection, ProvidesDemo
{
    public function __construct(private readonly PropertiesDemo $demo) {}

    public function id(): string
    {
        return 'catalog-properties';
    }

    public function title(): string
    {
        return (string) __('webx-catalog-properties::module.title');
    }

    public function icon(): string
    {
        return 'sliders';
    }

    public function order(): int
    {
        return 312;
    }

    public function group(): string
    {
        return CatalogModule::GROUP;
    }

    public function navSection(): string
    {
        return CatalogModule::DICTIONARIES;
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
        return ['catalog'];
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }
}
