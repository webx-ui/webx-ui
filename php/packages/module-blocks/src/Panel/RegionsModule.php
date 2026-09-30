<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * "Site regions": the header and the footer made of blocks (§7 of the regions spec).
 *
 * A second entry of the blocks package rather than a tab inside "Blocks", because the two are
 * opened by different people: that one is behind `blocks.manage` — saving a type is running
 * Blade — and a region is content, edited by whoever edits the pages. It is set up once and then
 * rarely touched, so it lives under "System" beside the block constructor rather than among the
 * sections an editor opens every day.
 *
 * The permission is declared once, by the blocks module, so that the role editor lists it once;
 * this entry only answers to it.
 */
final class RegionsModule extends AbstractModule
{
    public function id(): string
    {
        return 'regions';
    }

    public function title(): string
    {
        return (string) __('webx-blocks::module.regions');
    }

    public function icon(): string
    {
        return 'sidebar';
    }

    /** Right after the block constructor (600) that its regions are made of. */
    public function order(): int
    {
        return 610;
    }

    public function group(): string
    {
        return 'system';
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [];
    }
}
