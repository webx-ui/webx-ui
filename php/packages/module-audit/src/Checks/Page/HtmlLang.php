<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * No `lang` on `<html>`, or one that disagrees with what the page's own hreflang line says it
 * is — `<html lang="en">` on the page every alternate calls `de`, the layout's language left on
 * every version.
 */
final class HtmlLang extends PageCheck
{
    protected const ID = 'html.lang';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->lang === null || trim($page->lang) === '') {
            yield $this->on($page, 'lang-missing');

            return;
        }

        foreach ($page->hreflang ?? [] as $alternate) {
            if ($alternate['url'] !== $page->url || strtolower($alternate['lang']) === 'x-default') {
                continue;
            }

            if (self::language($alternate['lang']) !== self::language($page->lang)) {
                yield $this->on($page, 'lang-mismatch', ['lang' => $page->lang, 'hreflang' => $alternate['lang']]);

                return;
            }
        }
    }

    /** `en-GB` → `en`: the region may differ, the language may not. */
    private static function language(string $code): string
    {
        return strtolower(explode('-', str_replace('_', '-', trim($code)))[0]);
    }
}
