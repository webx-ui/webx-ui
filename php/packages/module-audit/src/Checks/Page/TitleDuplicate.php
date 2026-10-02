<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\Severity;

/**
 * The same title on several indexable pages.
 */
final class TitleDuplicate extends DuplicateCheck
{
    protected const ID = 'title.duplicate';

    protected const SEVERITY = Severity::WARNING;

    protected const COLUMN = 'title';

    protected const SUMMARY = 'duplicate-title';
}
