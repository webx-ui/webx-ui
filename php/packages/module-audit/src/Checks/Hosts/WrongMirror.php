<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hosts;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Page\LinkCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;

/**
 * A link to the site through its other mirror, or over `http` on an `https` site — every click a 301.
 */
final class WrongMirror extends LinkCheck
{
    protected const ID = 'hosts.wrong_mirror';

    protected const SEVERITY = Severity::WARNING;

    protected const GROUP = 'hosts';

    protected const SUMMARY = 'wrong-mirror';

    protected function links(AuditContext $context): Builder
    {
        $https = $context->scheme() === 'https';

        return $this->query($context)->where(static fn (Builder $wrong) => $wrong
            ->where('host_class', HostClassifier::OWN_MIRROR)
            ->when($https, static fn (Builder $plain) => $plain->orWhere(
                static fn (Builder $own) => $own->where('host_class', HostClassifier::OWN)->where('to_url', 'like', 'http://%'),
            )));
    }
}
