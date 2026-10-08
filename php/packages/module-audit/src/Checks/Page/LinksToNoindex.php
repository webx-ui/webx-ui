<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;

/**
 * Internal links to pages closed from search — `noindex` or robots.txt. A sign-in or a cart
 * link is what it should be; a product or an article on this list is closed by mistake.
 */
final class LinksToNoindex extends LinkCheck
{
    protected const ID = 'links.to_noindex';

    protected const SEVERITY = Severity::NOTICE;

    protected const SUMMARY = 'links-to-noindex';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)
            ->where('kind', AuditLink::A)
            ->whereHas('to', static fn (Builder $to) => $to->html()->where(
                static fn (Builder $closed) => $closed
                    ->where('blocked_by_robots', true)
                    ->orWhere('robots_meta', 'like', '%noindex%')
                    ->orWhere('robots_meta', 'like', '%none%')
                    ->orWhere('x_robots_tag', 'like', '%noindex%'),
            ));
    }
}
