<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\CatalogCollections\CatalogCollectionsServiceProvider;

/**
 * Collections as an entry of the «Catalog» group, below «Landings» (§6 of the collections spec).
 *
 * No permissions of their own: the catalogue's `catalog.view` reads, `catalog.manage` writes.
 */
final class CollectionsModule extends AbstractModule implements HasVisibility
{
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
}
