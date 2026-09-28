<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Panel;

/**
 * Where tariffs are written, ordered and published (§5.2).
 *
 * The panel's usual pair: `tariffs.view` opens the list, `tariffs.manage` writes — including the
 * order, which is what a reader of a page of prices sees first.
 */
final class TariffsModule extends TariffsGroup
{
    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'tariffs';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-tariffs::module.tariffs');
    }

    public function icon(): string
    {
        return 'tag';
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
        return ['tariffs.view', 'tariffs.manage'];
    }
}
