<?php

declare(strict_types=1);

namespace WebxUi\Team\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * Where the people of the team are written, ordered and published (§5.6): one entry of the menu,
 * at the top level — with no categories there is nothing to group it with.
 *
 * The panel's usual pair: `team.view` opens the list, `team.manage` writes — including the order,
 * which is what a reader of a team block sees first.
 */
final class TeamModule extends AbstractModule
{
    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'team';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-team::module.team');
    }

    public function icon(): string
    {
        return 'users';
    }

    public function order(): int
    {
        return 650;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['team.view', 'team.manage'];
    }
}
