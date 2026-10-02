<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Pictures without `width` and `height` — the page jumps while they load.
 */
final class ImagesDimensions extends PageCheck
{
    protected const ID = 'images.dimensions';

    protected const GROUP = 'images';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $count = (int) $page->fact('images_without_size', 0);

        if ($count > 0) {
            yield $this->on($page, 'images-dimensions', ['count' => $count]);
        }
    }
}
