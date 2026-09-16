<?php

declare(strict_types=1);

namespace WebxUi\Pages\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * The section where the site's pages are edited.
 *
 * In the content group and first in it: a site is its pages, and every other content module
 * that arrives later is something more specific than one. Two permissions, the panel's usual
 * pair — `view` opens the section and the picker a link field would use, `manage` writes.
 */
final class PagesModule extends AbstractModule
{
    public function id(): string
    {
        return 'pages';
    }

    public function title(): string
    {
        return (string) __('webx-pages::module.title');
    }

    public function icon(): string
    {
        return 'file';
    }

    public function order(): int
    {
        return 200;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['pages.view', 'pages.manage'];
    }
}
