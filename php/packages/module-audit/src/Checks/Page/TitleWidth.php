<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A title wider than the results show: they cut it at about 600 pixels of 20 px Arial, so a
 * title of capitals and wide letters is cut sooner than its characters say ({@see SerpWidth}).
 */
final class TitleWidth extends PageCheck
{
    protected const ID = 'title.width';

    protected const SEVERITY = Severity::NOTICE;

    /** The size the results set a title at. */
    private const SIZE = 20;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->title === null || $page->title === '') {
            return;
        }

        $max = $context->threshold('title_px', 600);
        $width = SerpWidth::of($page->title, self::SIZE);

        if ($width > $max) {
            yield $this->on($page, 'title-wide', ['width' => $width, 'max' => $max]);
        }
    }
}
