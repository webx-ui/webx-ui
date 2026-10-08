<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A canonical on another domain, another mirror or plain `http` — the page hands its place in
 * the results to an address that is not this site as visitors see it. Usually `APP_URL` or a
 * copied template.
 */
final class CanonicalForeign extends CanonicalCheck
{
    protected const ID = 'canonical.foreign';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $target = self::points($page);

        if ($target === null) {
            return;
        }

        $class = $context->hosts->classify($target);
        $scheme = (string) parse_url($target, PHP_URL_SCHEME);

        if ($class !== HostClassifier::OWN || $scheme !== $context->scheme()) {
            yield $this->on($page, 'canonical-foreign', ['url' => $target]);
        }
    }
}
