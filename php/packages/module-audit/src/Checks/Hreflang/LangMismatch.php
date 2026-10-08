<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hreflang;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A page calls a version `de`, and the version calls itself `de-AT`: the two do not describe the
 * same set, and search engines may drop the pair.
 */
final class LangMismatch extends HreflangCheck
{
    protected const ID = 'hreflang.lang_mismatch';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $rows = [];

        foreach ($page->hreflang ?? [] as $alternate) {
            if ($alternate['url'] === $page->url || strtolower($alternate['lang']) === 'x-default') {
                continue;
            }

            $target = $this->target($context, $alternate['url']);
            $own = $target === null ? null : self::codeOf($target, $target->url);

            if ($own !== null && strtolower($own) !== strtolower($alternate['lang'])) {
                $rows[] = ['url' => $alternate['url'], 'lang' => $alternate['lang'], 'value' => $own];
            }
        }

        if ($rows !== []) {
            yield $this->on($page, 'hreflang-lang-mismatch', ['count' => count($rows)], [
                'columns' => [Finding::column('url', 'url'), Finding::column('lang'), Finding::column('value')],
                'rows' => $rows,
            ]);
        }
    }

    /** The code a page gives an address in its own list, x-default aside. */
    private static function codeOf(AuditPage $page, string $url): ?string
    {
        foreach ($page->hreflang ?? [] as $alternate) {
            if ($alternate['url'] === $url && strtolower($alternate['lang']) !== 'x-default') {
                return $alternate['lang'];
            }
        }

        return null;
    }
}
