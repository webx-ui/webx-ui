<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\AuditLink;

/**
 * `rel=nofollow` on a link to the site itself — the site asking search engines not to follow it around.
 */
final class NofollowInternal extends LinkCheck
{
    protected const ID = 'links.nofollow_internal';

    protected const SEVERITY = Severity::NOTICE;

    protected const SUMMARY = 'nofollow-internal';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)->where('kind', AuditLink::A)->where('host_class', HostClassifier::OWN)->where('rel', 'like', '%nofollow%');
    }
}
