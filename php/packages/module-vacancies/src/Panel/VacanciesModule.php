<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Vacancies\Demo\VacanciesDemo;
use WebxUi\Vacancies\Mcp\VacanciesResources;
use WebxUi\Vacancies\Mcp\VacancyTools;

/**
 * Where vacancies are written, published and closed (§4.10): `vacancies.view` opens the list,
 * `vacancies.manage` writes.
 *
 * To an agent it is the same section by other doors (§4.12): eleven tools behind
 * `vacancies:read` and `vacancies:write`, and `vacancies://catalog` to read first. The demo seeds
 * the categories with the vacancies, because a vacancy in two categories is part of what it shows.
 */
final class VacanciesModule extends VacanciesGroup implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'vacancies';

    public function __construct(
        private readonly VacancyTools $tools,
        private readonly VacanciesResources $resources,
        private readonly VacanciesDemo $demo,
    ) {}

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

    /**
     * Worked out, not written down: the inbox only where it is installed (§4.13).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->demo->requires();
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return $this->resources->all();
    }
}
