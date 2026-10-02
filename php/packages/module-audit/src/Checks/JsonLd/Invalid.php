<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\JsonLd;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Page\PageCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A JSON-LD block that does not parse — a stray comma from a template, an unescaped quote in a
 * title. The whole block is lost, not only the broken field.
 */
final class Invalid extends PageCheck
{
    protected const ID = 'jsonld.invalid';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $rows = [];

        foreach ($page->json_ld ?? [] as $index => $block) {
            if ($block['error'] !== null) {
                $rows[] = ['block' => $index + 1, 'error' => $block['error']];
            }
        }

        if ($rows !== []) {
            yield $this->on($page, 'jsonld-invalid', ['count' => count($rows)], [
                'columns' => [Finding::column('block'), Finding::column('error')],
                'rows' => $rows,
            ]);
        }
    }
}
