<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\AuditLink;

/**
 * Internal links with `utm_` tags — usually copied from a newsletter. Every click starts a new
 * visit from that campaign in the analytics, and every tagged address is a copy of the page.
 */
final class LinksUtm extends LinkCheck
{
    protected const ID = 'links.utm';

    protected const SEVERITY = Severity::WARNING;

    protected const SUMMARY = 'links-utm';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)
            ->where('kind', AuditLink::A)
            ->where('host_class', HostClassifier::OWN)
            ->where('to_url', 'like', '%utm%');
    }

    protected function keep(AuditLink $link, AuditContext $context): bool
    {
        return preg_match('~[?&]utm_~i', $link->to_url) === 1;
    }
}
