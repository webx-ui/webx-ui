<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The categories of the events — the formats a site runs: flat, ordered by hand, several per
 * event, each a page of the site. The panel's shared category screens with this module's words.
 *
 * Its own permission, because renaming a section of the site is a different job from writing an
 * event in it.
 *
 * To an agent, the tools every module's categories have: `event_categories_list`, and create,
 * update, delete and reorder. Filing an event into a category is still `events_update`.
 */
final class CategoriesModule extends EventsGroup implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'event-categories';

    public function __construct(private readonly CategoryForm $form) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-events::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 610;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [EventCategory::MANAGE];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(EventCategory::class, $this->form))->all();
    }
}
