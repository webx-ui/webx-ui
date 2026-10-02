<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\JsonLd;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Page\PageCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A `Product`, `Article`, `Event`, `JobPosting`, `FAQPage` or `BreadcrumbList` without a field
 * the search engines require — the page loses its rich result, and the console reports the
 * markup as invalid.
 */
final class Required extends PageCheck
{
    protected const ID = 'jsonld.required';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $rows = [];

        foreach ($page->json_ld ?? [] as $index => $block) {
            foreach ($block['items'] ?? [] as $item) {
                if ($item['missing'] !== []) {
                    $rows[] = ['block' => $index + 1, 'type' => $item['type'], 'fields' => implode(', ', $item['missing'])];
                }
            }
        }

        if ($rows !== []) {
            yield $this->on($page, 'jsonld-required', ['count' => count($rows)], [
                'columns' => [Finding::column('block'), Finding::column('type'), Finding::column('fields')],
                'rows' => $rows,
            ]);
        }
    }
}
