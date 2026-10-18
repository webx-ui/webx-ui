<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;

/**
 * The tree of categories as an entry of its own in the `catalog` group, beside the products.
 *
 * A module only because the navigation is one entry per module: the tree is opened often enough
 * to want one, and a button in the head of the products was a step too far. It owns nothing —
 * no permissions (categories go by `catalog.*`, the people who file products arrange the
 * shelves), no tools (they are `catalog`'s), no demo.
 */
final class CatalogCategoriesModule extends AbstractModule implements HasVisibility
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
     * No permission of its own: a dictionary of the catalogue opens to whoever may read or edit
     * the catalogue, the same permissions its routes ask for (WEBX_UI_PERMISSIONS.md §11).
     *
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
}
