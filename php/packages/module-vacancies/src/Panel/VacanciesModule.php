<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

/**
 * Where vacancies are written, published and closed (§4.10): `vacancies.view` opens the list,
 * `vacancies.manage` writes.
 */
final class VacanciesModule extends VacanciesGroup
{
    public const ID = 'vacancies';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-vacancies::module.vacancies');
    }

    public function icon(): string
    {
        return 'briefcase';
    }

    public function order(): int
    {
        return 670;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['vacancies.view', 'vacancies.manage'];
    }
}
