<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * `<img>` without an `alt` attribute. An empty `alt` is a decision — a decorative picture — and passes.
 */
final class ImagesAlt extends PageCheck
{
    protected const ID = 'images.alt';

    protected const GROUP = 'images';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->images_without_alt > 0) {
            yield $this->on($page, 'images-alt', ['count' => $page->images_without_alt]);
        }
    }
}
