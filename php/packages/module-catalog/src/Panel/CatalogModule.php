<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * The catalogue as a section of the panel: products, categories and «Deleted», in a group of its
 * own that the satellites join — brands, stock, labels stand beside it, not inside it (§1).
 *
 * Three permissions (§11.5): `view` opens the section, `manage` writes everything but deletes
 * and restores, `delete` does those and opens «Deleted». Categories go by the same three: the
 * people who file products are the people who arrange the shelves.
 */
final class CatalogModule extends AbstractModule
{
    /** The id of the navigation group the satellites join as well. */
    public const GROUP = 'catalog';

    public function id(): string
    {
        return 'catalog';
    }

    public function title(): string
    {
        return (string) __('webx-catalog::module.title');
    }

    public function icon(): string
    {
        return 'cart';
    }

    public function order(): int
    {
        return 300;
    }

    public function group(): string
    {
        return self::GROUP;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['catalog.view', 'catalog.manage', 'catalog.delete'];
    }
}
