<?php

declare(strict_types=1);

namespace WebxUi\Users\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Users\Demo\UsersDemo;
use WebxUi\Users\Mcp\UsersResources;
use WebxUi\Users\Mcp\UsersTools;

/**
 * «Customers» in the panel (spec §16, decision 20): the list and the card of a visitor. The id,
 * the permissions, the tables and the tools stay `users`; the word is the dictionary's
 * (`webx-users::module.title`), which a site overrides with a line of its own.
 *
 * The module declares the three permissions of the section — the groups and the settings beside
 * it are opened by them and declare none (§16.1). A visitor is personal data: by default only the
 * «Administrator» holds them (decision 22).
 *
 * To an agent it is the same section by other doors (§17): the `users_*` tools behind
 * `users:read` and `users:write` — the settings behind `users.settings` as in the panel — and
 * `users://catalog` to read first. The demo seeds two groups and five visitors without passwords.
 */
final class UsersModule extends AbstractModule implements HasVisibility, ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const string ID = 'users';

    /** The menu group the three entries stand in. */
    public const string GROUP = 'users';

    /** The list, the card, the groups — reading; and what the agent reads (`users:read`). */
    public const string VIEW = 'users.view';

    /** The profile and the fields, the groups of a visitor, the block, the letters, create and delete; the groups themselves. */
    public const string MANAGE = 'users.manage';

    /** The screen `users.settings`: the registration, the fields, the keys of the providers. */
    public const string SETTINGS = 'users.settings';

    public function __construct(
        private readonly UsersTools $tools,
        private readonly UsersResources $resources,
        private readonly UsersDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-users::module.customers');
    }

    public function icon(): string
    {
        return 'users';
    }

    public function order(): int
    {
        return 400;
    }

    public function group(): string
    {
        return self::GROUP;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [self::VIEW, self::MANAGE, self::SETTINGS];
    }

    /**
     * The settings alone do not open the list of people.
     *
     * @return list<string>
     */
    public function visibleWith(): array
    {
        return [self::VIEW, self::MANAGE];
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

    /**
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
}
