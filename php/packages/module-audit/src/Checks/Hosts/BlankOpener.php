<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hosts;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Page\LinkCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\AuditLink;

/**
 * `target=_blank` to somebody else’s site without `rel=noopener`.
 */
final class BlankOpener extends LinkCheck
{
    protected const ID = 'hosts.blank_opener';

    protected const SEVERITY = Severity::NOTICE;

    protected const GROUP = 'hosts';

    protected const SUMMARY = 'blank-opener';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)
            ->where('kind', AuditLink::A)
            ->where('target', '_blank')
            ->where('host_class', '<>', HostClassifier::OWN)
            ->where(static fn (Builder $rel) => $rel->whereNull('rel')->orWhere(
                static fn (Builder $open) => $open->where('rel', 'not like', '%noopener%')->where('rel', 'not like', '%noreferrer%'),
            ));
    }
}
