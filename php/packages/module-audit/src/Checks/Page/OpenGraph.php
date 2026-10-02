<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * No `og:title`, `og:image` or `og:url` — a shared link looks like a bare address.
 */
final class OpenGraph extends PageCheck
{
    protected const ID = 'og.missing';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $missing = array_values(array_filter(['title', 'image', 'url'], static fn (string $tag): bool => trim((string) ($page->og[$tag] ?? '')) === ''));

        if ($missing !== []) {
            yield $this->on($page, 'og-missing', ['tags' => implode(', ', array_map(static fn (string $tag): string => 'og:'.$tag, $missing))]);
        }
    }
}
