<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Panel;

use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Tariffs\Models\TariffCategory;

/**
 * The groups of tariffs: flat, ordered by hand, several per tariff — the panel's shared category
 * screens with this module's words (`TariffCategory::categoryKind()`), and no address.
 *
 * Its own permission, for the reason every module's categories have one: the tabs of a page of
 * prices are a different job from the price on a card.
 *
 * To an agent, the tools every module's categories have: the id is `tariff-groups`, so they are
 * `tariff_groups_list`, and behind `tariff-groups:write` create, update, delete and reorder
 * (decision 14 — the editor and the agent see groups, the code sees categories).
 */
final class CategoriesModule extends TariffsGroup implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'tariff-groups';

    public function __construct(private readonly CategoryForm $form) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-tariffs::module.groups');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 690;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [TariffCategory::MANAGE];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return (new CategoryTools(TariffCategory::class, $this->form, $this->id()))->all();
    }
}
