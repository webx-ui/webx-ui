<?php

declare(strict_types=1);

namespace WebxUi\Press\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * Where outlets and their articles are written, ordered and published (§4.9).
 *
 * One entry in the menu and no group of its own: the module has one section. The panel's usual
 * pair of permissions — `press.view` opens the list, `press.manage` writes, including the order,
 * which is what a reader of the strip of logos sees first.
 */
final class PressModule extends AbstractModule
{
    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'press';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-press::module.press');
    }

    public function icon(): string
    {
        return 'newspaper';
    }

    public function order(): int
    {
        return 620;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['press.view', 'press.manage'];
    }
}
