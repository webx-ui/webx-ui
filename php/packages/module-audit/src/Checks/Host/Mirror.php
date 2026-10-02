<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * `www` and the bare name both answer 200: two copies of every page for a search engine to
 * choose between. A second name that does not resolve at all is only worth knowing.
 */
final class Mirror extends Check
{
    protected const ID = 'host.mirror';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::ERROR;

    public function run(AuditContext $context): iterable
    {
        $mirror = $context->probes->get('mirror');
        $home = $context->probes->get('home');

        if ($mirror === null || $home === null) {
            return;
        }

        $host = (string) parse_url($mirror->url, PHP_URL_HOST);

        if ($mirror->status === null) {
            yield $this->found('mirror-unresolved', ['host' => $host], $mirror->url, Severity::NOTICE);
        } elseif ($mirror->ok() && $home->ok()) {
            yield $this->found('mirror-answers', ['host' => $host], $mirror->url);
        }
    }
}
