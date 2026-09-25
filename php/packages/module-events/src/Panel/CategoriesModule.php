<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

use WebxUi\Events\Models\EventCategory;

/**
 * The categories of the events — the formats a site runs: flat, ordered by hand, several per
 * event, each a page of the site. The panel's shared category screens with this module's words.
 *
 * Its own permission, because renaming a section of the site is a different job from writing an
 * event in it.
 */
final class CategoriesModule extends EventsGroup
{
    public const ID = 'event-categories';

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
}
