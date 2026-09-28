<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Vacancies\Models\VacancyCategory;

/**
 * The categories of the vacancies — "Development", "Sales": flat, ordered by hand, several per
 * vacancy, the groups and the filter of the index and never a page (decision 2). The panel's
 * shared category screens with this module's words.
 *
 * Its own permission, because renaming a group of the careers page is a different job from
 * writing a vacancy in it.
 *
 * To an agent, the tools every module's categories have: `vacancy_categories_list`, and create,
 * update, delete and reorder. A category without an address is named there by its title; its slug —
 * the key of the filter — is in the values they answer with and in `vacancies://catalog`. Filing a
 * vacancy into a category is still `vacancies_update`.
 */
final class CategoriesModule extends VacanciesGroup implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'vacancy-categories';

    public function __construct(private readonly CategoryForm $form) {}

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

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(VacancyCategory::class, $this->form))->all();
    }
}
