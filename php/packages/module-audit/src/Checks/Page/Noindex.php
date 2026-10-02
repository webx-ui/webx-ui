<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Every page with `noindex`, to look through: a search page meant to be closed, or a section closed by mistake.
 */
final class Noindex extends PageCheck
{
    protected const ID = 'indexing.noindex';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::NOTICE;

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->where('source', '<>', AuditPage::HOME);
    }

    /** Which of the two said it — the meta tag or the header. */
    public static function source(AuditPage $page): string
    {
        return str_contains(strtolower((string) $page->robots_meta), 'noindex') ? 'meta robots' : 'X-Robots-Tag';
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->noindex()) {
            yield $this->on($page, 'noindex', ['source' => self::source($page)]);
        }
    }
}
