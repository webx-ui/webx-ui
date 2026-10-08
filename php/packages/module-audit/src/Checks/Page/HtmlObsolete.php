<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Tags HTML dropped — `<font>`, `<center>`, `<marquee>` — and Flash. They usually come with a
 * text pasted from Word or an old site, and bring their own fonts and colours along.
 */
final class HtmlObsolete extends PageCheck
{
    protected const ID = 'html.obsolete';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $tags = $page->fact('obsolete_tags');

        if (! is_array($tags) || $tags === []) {
            return;
        }

        $rows = [];

        foreach ($tags as $tag => $count) {
            $rows[] = ['markup' => $tag === 'flash' ? 'Flash' : '<'.$tag.'>', 'count' => (int) $count];
        }

        yield $this->on($page, 'html-obsolete', ['tags' => implode(', ', array_column($rows, 'markup'))], [
            'columns' => [Finding::column('markup', 'code'), Finding::column('count')],
            'rows' => $rows,
        ]);
    }
}
