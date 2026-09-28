<?php

declare(strict_types=1);

namespace WebxUi\Banners\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * Where the banners are put into places, ordered and turned on (§5.4): one entry of the menu, at
 * the top level, after the team.
 *
 * The panel's usual pair: `banners.view` opens the places and their banners, `banners.manage`
 * writes — the banners, their order and the places of somebody's own (§5.5).
 */
final class BannersModule extends AbstractModule
{
    /** The id in the panel — and the name `webx:setup` knows it by. */
    public const ID = 'banners';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-banners::module.banners');
    }

    public function icon(): string
    {
        return 'image';
    }

    public function order(): int
    {
        return 660;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['banners.view', 'banners.manage'];
    }
}
