<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\CatalogStock\Demo\StockDemo;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * Stock statuses as an entry of the «Catalog» group, under its «Dictionaries» caption (§5 of the
 * dictionaries spec): the panel's shared category screens with the words of stock.
 *
 * No permissions of its own (decision 8). To an agent, the tools every module's categories have —
 * `catalog_stock_list`, `_create`, `_update`, `_delete`, `_reorder`; putting a product into a
 * status is `catalog_products_update` with `stock.status`, or `catalog_bulk` with `set-stock`.
 */
final class StockModule extends AbstractModule implements HasNavSection, ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(
        private readonly CategoryForm $form,
        private readonly StockDemo $demo,
    ) {}

    public function id(): string
    {
        return 'catalog-stock';
    }

    public function title(): string
    {
        return (string) __('webx-catalog-stock::module.title');
    }

    public function icon(): string
    {
        return 'check-circle';
    }

    public function order(): int
    {
        return 311;
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

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(StockStatus::class, $this->form, $this->id()))->all();
    }
}
