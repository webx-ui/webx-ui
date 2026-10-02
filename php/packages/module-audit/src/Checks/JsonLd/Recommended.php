<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\JsonLd;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Page\PageCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * The fields that make a rich result richer — an article's description, a product's brand —
 * missing. Valid markup, a plainer snippet.
 */
final class Recommended extends PageCheck
{
    protected const ID = 'jsonld.recommended';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $rows = [];

        foreach ($page->json_ld ?? [] as $index => $block) {
            foreach ($block['items'] ?? [] as $item) {
                if ($item['recommended'] !== []) {
                    $rows[] = ['block' => $index + 1, 'type' => $item['type'], 'fields' => implode(', ', $item['recommended'])];
                }
            }
        }

        if ($rows !== []) {
            yield $this->on($page, 'jsonld-recommended', ['count' => count($rows)], [
                'columns' => [Finding::column('block'), Finding::column('type'), Finding::column('fields')],
                'rows' => $rows,
            ]);
        }
    }
}
