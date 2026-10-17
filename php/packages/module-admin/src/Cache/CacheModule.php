<?php

declare(strict_types=1);

namespace WebxUi\Admin\Cache;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Admin\Panel\SystemSections;

/**
 * «System → Cache» (WEBX_UI_CACHE.md §5): the store the site caches in, every group with its
 * version, and «Reset the site cache» — a group at a time or all of them.
 *
 * Two permissions: `cache.view` opens the page, `cache.reset` presses the button. No role gets
 * either when the frame is installed, so only a super administrator sees the section until one is
 * granted — a reset is an admission that a `bump` is missing somewhere, and not an everyday act.
 */
class CacheModule extends AbstractModule implements HasNavSection
{
    public const ID = 'cache';

    public const VIEW = 'cache.view';

    public const RESET = 'cache.reset';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-admin::cache.title');
    }

    public function icon(): string
    {
        return 'refresh';
    }

    /** After the search index: the caption is «Search and checks», and this is a check. */
    public function order(): int
    {
        return 730;
    }

    public function group(): string
    {
        return SystemSections::GROUP;
    }

    public function navSection(): string
    {
        return SystemSections::SEARCH;
    }

    public function permissions(): array
    {
        return [self::VIEW, self::RESET];
    }
}
