<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Catalog\Panel\CatalogModule;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * Labels as an entry of the «Catalog» group, under its «Dictionaries» caption (§5 of the
 * dictionaries spec): the panel's shared category screens with the words of labels.
 *
 * No permissions of its own (decision 8): the people who edit products put labels on them, so
 * `catalog.view` reads the list and `catalog.manage` writes it. To an agent, the tools every
 * module's categories have — `catalog_labels_list`, `_create`, `_update`, `_delete`,
 * `_reorder`; putting a label on a product is `catalog_products_update` with `labels.ids`, or
 * `catalog_bulk` with `add-label`.
 */
final class LabelsModule extends AbstractModule implements HasNavSection, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly CategoryForm $form) {}

    public function id(): string
    {
        return 'catalog-labels';
    }

    public function title(): string
    {
        return (string) __('webx-catalog-labels::module.title');
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function order(): int
    {
        return 310;
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
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(Label::class, $this->form, $this->id()))->all();
    }
}
