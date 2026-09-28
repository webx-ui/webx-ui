<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use WebxUi\Vacancies\Models\VacancyCategory;

/**
 * The categories of the vacancies — "Development", "Sales": flat, ordered by hand, several per
 * vacancy, the groups and the filter of the index and never a page (decision 2). The panel's
 * shared category screens with this module's words.
 *
 * Its own permission, because renaming a group of the careers page is a different job from
 * writing a vacancy in it.
 */
final class CategoriesModule extends VacanciesGroup
{
    public const ID = 'vacancy-categories';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-vacancies::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 680;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [VacancyCategory::MANAGE];
    }
}
