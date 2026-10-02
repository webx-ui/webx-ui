<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\Severity;

/**
 * The same text on several indexable pages, by the hash of the visible text.
 */
final class ContentDuplicate extends DuplicateCheck
{
    protected const ID = 'content.duplicate';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::WARNING;

    protected const COLUMN = 'text_hash';

    protected const SUMMARY = 'duplicate-text';
}
