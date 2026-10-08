<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * The site opens at its bare IP address: one more copy of every page, under an address nobody
 * meant to publish. The server's default host should redirect to the domain or answer an error.
 * Another site answering there is not this check's business — only the same home page is.
 */
final class ByIp extends Check
{
    protected const ID = 'host.ip';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        $home = $context->probes->get('home');
        $ip = $context->probes->get('ip');

        if ($home === null || $ip === null || $ip->status !== 200 || ! $home->ok()) {
            return;
        }

        $title = self::title($home->body);

        if ($title !== null && $title === self::title($ip->body)) {
            yield $this->found('host-ip', ['url' => $ip->url], $ip->url);
        }
    }

    private static function title(string $html): ?string
    {
        return preg_match('~<title[^>]*>(.*?)</title>~is', $html, $match) === 1
            ? trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5)))
            : null;
    }
}
