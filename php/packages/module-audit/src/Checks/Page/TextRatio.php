<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Visible text under the share of the HTML — the page drowns in markup, however many words it has.
 */
final class TextRatio extends PageCheck
{
    protected const ID = 'content.text_ratio';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::NOTICE;

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->where('indexable', true);
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $ratio = (float) $page->fact('text_ratio', 0);
        $threshold = $context->threshold('text_ratio', 10);

        if ($ratio < $threshold) {
            yield $this->on($page, 'text-ratio', ['ratio' => $ratio, 'threshold' => $threshold, 'words' => (int) $page->word_count]);
        }
    }
}
