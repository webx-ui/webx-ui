<?php

declare(strict_types=1);

namespace WebxUi\Services\Panel;

use WebxUi\Services\Models\ServiceCategory;

/**
 * The sections of the catalogue: flat, ordered by hand, several per service — the panel's shared
 * category screens, with this module's words (`ServiceCategory::categoryKind()`).
 *
 * Its own permission, because renaming a section of the site's catalogue is a different job from
 * writing a service in it.
 */
final class CategoriesModule extends ServicesGroup
{
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
}
