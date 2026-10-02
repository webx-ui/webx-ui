<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\Severity;

/**
 * The same meta description on several indexable pages.
 */
final class DescriptionDuplicate extends DuplicateCheck
{
    protected const ID = 'description.duplicate';

    protected const SEVERITY = Severity::WARNING;

    protected const COLUMN = 'description';

    protected const SUMMARY = 'duplicate-description';
}
