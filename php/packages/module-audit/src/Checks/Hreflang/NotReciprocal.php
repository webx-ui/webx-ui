<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hreflang;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A names B as its other language, B does not name A back. Search engines ignore a pair that
 * is not confirmed from both sides. The expansion is the whole matrix of the page.
 */
final class NotReciprocal extends HreflangCheck
{
    protected const ID = 'hreflang.not_reciprocal';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $rows = [];
        $missing = 0;

        foreach ($page->hreflang ?? [] as $alternate) {
            $target = $alternate['url'] === $page->url ? $page : $this->target($context, $alternate['url']);
            $back = $target === null || ! $target->html() ? null : ($target === $page || self::names($target, $page->url));

            if ($back === false) {
                $missing++;
            }

            $rows[] = [
                'lang' => $alternate['lang'],
                'url' => $alternate['url'],
                'status' => $target?->status,
                'back' => $back,
                'indexable' => $target?->indexable,
            ];
        }

        if ($missing > 0) {
            yield $this->on($page, 'hreflang-not-reciprocal', ['count' => $missing], [
                'columns' => [
                    Finding::column('lang'),
                    Finding::column('url', 'url'),
                    Finding::column('status', 'status'),
                    Finding::column('back', 'bool'),
                    Finding::column('indexable', 'bool'),
                ],
                'rows' => $rows,
            ]);
        }
    }
}
