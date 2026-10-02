<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hosts;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;

/**
 * A host that looks like the site's own: the same first word in another zone — `shop.local`
 * while the site is `shop.com`, `shop.dev-server.net`. Not certainly a stand, so a warning to
 * look at rather than an error (§5.6).
 */
final class Similar extends HostCheck
{
    protected const ID = 'hosts.similar';

    protected const SEVERITY = Severity::WARNING;

    protected const SUMMARY = 'similar';

    protected function hosts(AuditContext $context, array $hosts): array
    {
        $word = self::word($context->host());

        if ($word === '' || strlen($word) < 3) {
            return [];
        }

        return array_values(array_filter(
            $hosts,
            fn (string $host): bool => $context->hosts->classifyHost($host) === 'external' && self::word($host) === $word,
        ));
    }

    /** `www.shop.com` → `shop`. */
    private static function word(string $host): string
    {
        $host = strtolower($host);

        return explode('.', str_starts_with($host, 'www.') ? substr($host, 4) : $host)[0];
    }
}
