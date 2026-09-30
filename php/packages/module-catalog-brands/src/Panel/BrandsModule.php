<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * Brands as an entry of the «Catalog» group, right after «Products» and above the «Dictionaries»
 * caption (§5 of the dictionaries spec): a brand has a page of its own, which makes it more than a
 * reference list. The panel's shared category screens with the words of brands.
 *
 * No permissions of its own (decision 8). To an agent, the tools every module's categories have —
 * `catalog_brands_list`, `_create`, `_update`, `_delete`, `_reorder`; a product's brand is
 * `catalog_products_update` with `brand.id`, or `catalog_bulk` with `set-brand`.
 */
final class BrandsModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly CategoryForm $form) {}

    public function id(): string
    {
        return 'catalog-brands';
    }

    public function title(): string
    {
        return (string) __('webx-catalog-brands::module.title');
    }

    public function icon(): string
    {
        return 'star';
    }

    public function order(): int
    {
        return 302;
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
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(Brand::class, $this->form, $this->id()))->all();
    }
}
