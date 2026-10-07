<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hosts;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Page\LinkCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\ContentUrl;

/**
 * A link or a picture written with the site’s own host — it works, and breaks at the next move.
 *
 * Only what somebody wrote into the content: the address has to be in a field of a record
 * (`audit_content_urls`, the same scan `hosts.dev_content` reads). Everything the system prints
 * is absolute by design — an entity's address, a card's link, a picture of the library — and
 * listing those on every page buried the links an editor pasted, which is what this check is
 * for, under notices nobody could act on.
 */
final class AbsoluteOwn extends LinkCheck
{
    protected const ID = 'hosts.absolute_own';

    protected const SEVERITY = Severity::NOTICE;

    protected const GROUP = 'hosts';

    protected const SUMMARY = 'absolute-own';

    /** @var list<string> */
    protected const NEEDS = ['crawl', 'database'];

    protected function links(AuditContext $context): Builder
    {
        $written = ContentUrl::query()
            ->select('url')
            ->where('run_id', $context->run->id)
            ->where('host_class', HostClassifier::OWN);

        return $this->query($context)
            ->whereIn('kind', [AuditLink::A, AuditLink::IMG])
            ->where('host_class', HostClassifier::OWN)
            ->where('absolute', true)
            ->whereIn('to_url', $written);
    }
}
