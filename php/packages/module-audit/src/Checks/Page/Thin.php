<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Fewer words than the threshold on an indexable page.
 */
final class Thin extends PageCheck
{
    protected const ID = 'content.thin';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::NOTICE;

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->where('indexable', true);
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $words = (int) $page->word_count;

        if ($words < $context->threshold('thin_words', 250)) {
            yield $this->on($page, 'content-thin', ['count' => $words]);
        }
    }
}
