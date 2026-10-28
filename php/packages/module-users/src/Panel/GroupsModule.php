<?php

declare(strict_types=1);

namespace WebxUi\Users\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;

/**
 * «Customers → Groups» (spec §16.2): the list of the customer groups — a code, a name in one
 * language, how many visitors are in each. Read with `users.view`, written with `users.manage`.
 */
final class GroupsModule extends AbstractModule implements HasVisibility
{
    public const string ID = 'users-groups';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-users::module.groups');
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function order(): int
    {
        return 401;
    }

    public function group(): string
    {
        return UsersModule::GROUP;
    }

    /** The section's permissions are the customers'. */
    public function permissions(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function visibleWith(): array
    {
        return [UsersModule::VIEW, UsersModule::MANAGE];
    }
}
