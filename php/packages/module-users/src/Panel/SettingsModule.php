<?php

declare(strict_types=1);

namespace WebxUi\Users\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;

/**
 * «Customers → Settings» (spec §16.3): the registration, how much the forms ask for, the keys of
 * the social networks. Only `users.settings` opens it — a manager who looks people up is not the
 * one who changes the keys of the site's Google application.
 */
final class SettingsModule extends AbstractModule implements HasVisibility
{
    public const string ID = 'users-settings';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-users::module.settings');
    }

    public function icon(): string
    {
        return 'settings';
    }

    public function order(): int
    {
        return 402;
    }

    public function group(): string
    {
        return UsersModule::GROUP;
    }

    public function permissions(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function visibleWith(): array
    {
        return [UsersModule::SETTINGS];
    }
}
