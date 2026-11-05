<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;
use WebxUi\Catalog\Panel\CatalogModule;

/**
 * Feeds as an entry of the «Catalog» group, after «Landings» (§11 of the feeds spec).
 *
 * No permissions of their own: the catalogue's `catalog.view` reads, `catalog.manage` writes.
 */
final class CatalogFeedsModule extends AbstractModule implements HasVisibility
{
    public function id(): string
    {
        return 'catalog-feeds';
    }

    public function title(): string
    {
        return (string) __('webx-catalog-feeds::module.title');
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function order(): int
    {
        return 304;
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
        return ['catalog'];
    }
}
