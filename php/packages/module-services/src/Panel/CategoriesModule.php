<?php

declare(strict_types=1);

namespace WebxUi\Services\Panel;

use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Services\Models\ServiceCategory;

/**
 * The sections of the catalogue: flat, ordered by hand, several per service — the panel's shared
 * category screens, with this module's words (`ServiceCategory::categoryKind()`).
 *
 * Its own permission, because renaming a section of the site's catalogue is a different job from
 * writing a service in it.
 *
 * To an agent, the tools every module's categories have (§3.7): `service_categories_list`, and
 * behind `service-categories:write` create, update, delete and reorder. Filing a service into a
 * category is still `services_update`, and its place inside one is `services_reorder`.
 */
final class CategoriesModule extends ServicesGroup implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly CategoryForm $form) {}

    public function id(): string
    {
        return 'service-categories';
    }

    public function title(): string
    {
        return (string) __('webx-services::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 410;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [ServiceCategory::categoryKind()->manage];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(ServiceCategory::class, $this->form))->all();
    }
}
