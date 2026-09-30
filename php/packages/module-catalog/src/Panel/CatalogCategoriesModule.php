<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * The tree of categories as an entry of its own in the `catalog` group, beside the products.
 *
 * A module only because the navigation is one entry per module: the tree is opened often enough
 * to want one, and a button in the head of the products was a step too far. It owns nothing —
 * no permissions (categories go by `catalog.*`, the people who file products arrange the
 * shelves), no tools (they are `catalog`'s), no demo.
 */
final class CatalogCategoriesModule extends AbstractModule
{
    public function id(): string
    {
        return 'catalog-categories';
    }

    public function title(): string
    {
        return (string) __('webx-catalog::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 300;
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
