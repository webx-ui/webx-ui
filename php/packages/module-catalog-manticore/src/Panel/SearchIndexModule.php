<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Admin\Panel\SystemSections;
use WebxUi\Catalog\Manticore\IndexStatus;

/**
 * «System → Search index» (decision 27): the server, the tables against the database, the queue,
 * and the rebuild a person starts when the doctor says a table is out of date.
 *
 * Only on the Manticore engine: a site whose database is the index has nothing to show here, and
 * the section stays out of the menu rather than explaining a server that is not there. The rebuild
 * is minutes of load on a large catalogue, so it has a permission of its own.
 */
final class SearchIndexModule extends AbstractModule implements HasNavSection
{
    public const ID = 'search-index';

    public function __construct(private readonly IndexStatus $status) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-catalog-manticore::panel.title');
    }

    public function icon(): string
    {
        return 'list';
    }

    /** After «SEO»: both are about being found. */
    public function order(): int
    {
        return 720;
    }

    public function group(): string
    {
        return 'system';
    }

    public function navSection(): string
    {
        return SystemSections::SEARCH;
    }

    public function permissions(): array
    {
        return [self::ID.'.view', self::ID.'.manage'];
    }

    public function available(): bool
    {
        return $this->status->active();
    }
}
