<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * The encoding: said neither in `Content-Type` nor in `<meta charset>` — a notice, the browser
 * guesses — or said differently in the two — a warning, one of them is wrong and a visitor may
 * get the wrong one.
 */
final class HtmlCharset extends PageCheck
{
    protected const ID = 'html.charset';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if (! array_key_exists('charset_meta', $page->facts ?? [])) {
            return;
        }

        $meta = $page->fact('charset_meta');
        $header = preg_match('~charset=["\']?([\w-]+)~i', (string) $page->content_type, $match) === 1 ? $match[1] : null;

        if (! is_string($meta) && $header === null) {
            yield $this->on($page, 'html-charset-missing', severity: Severity::NOTICE);
        } elseif (is_string($meta) && $header !== null && self::canonical($meta) !== self::canonical($header)) {
            yield $this->on($page, 'html-charset-differs', ['meta' => $meta, 'header' => $header]);
        }
    }

    /** `UTF-8`, `utf8` and `utf-8` are one encoding. */
    private static function canonical(string $charset): string
    {
        return (string) preg_replace('~[^a-z0-9]~', '', strtolower($charset));
    }
}
