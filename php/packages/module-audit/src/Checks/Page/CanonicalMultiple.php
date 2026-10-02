<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\Urls;
use WebxUi\Audit\Runs\AuditPage;

/**
 * More than one canonical, or a tag and a `Link` header that disagree — search engines then ignore both.
 */
final class CanonicalMultiple extends PageCheck
{
    protected const ID = 'canonical.multiple';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        /** @var list<string> $tags */
        $tags = array_values(array_filter((array) $page->fact('canonicals', []), 'is_string'));
        $header = $page->fact('canonical_header');
        $addresses = [...$tags, ...(is_string($header) ? [$header] : [])];
        $distinct = array_unique(array_map(static fn (string $url): string => Urls::resolve($page->url, $url) ?? $url, $addresses));

        if (count($tags) > 1 || count($distinct) > 1) {
            yield $this->on($page, 'canonical-multiple', ['count' => count($addresses)], [
                'columns' => [Finding::column('url', 'url')],
                'rows' => array_map(static fn (string $url): array => ['url' => $url], $addresses),
            ]);
        }
    }
}
