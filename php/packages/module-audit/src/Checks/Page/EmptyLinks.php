<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Links with nothing to call them by: no text, no `aria-label`, and for a picture link no `alt`.
 */
final class EmptyLinks extends PageCheck
{
    protected const ID = 'links.empty';

    protected const GROUP = 'links';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $empty = (array) $page->fact('links_empty', []);
        $text = (int) ($empty['text'] ?? 0);
        $images = (int) ($empty['images'] ?? 0);

        if ($text + $images > 0) {
            yield $this->on($page, 'links-empty', ['images' => $images, 'text' => $text]);
        }
    }
}
