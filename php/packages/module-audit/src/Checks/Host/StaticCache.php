<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;

/**
 * CSS, JS and pictures without `Cache-Control`, or cached for less than a week: every page
 * downloads them again. Read off the home page's own files — the rule is the server's, so a
 * handful shows it.
 */
final class StaticCache extends Check
{
    protected const ID = 'host.static_cache';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        $minimum = $context->threshold('static_cache_seconds', 7 * 24 * 3600);
        $rows = [];

        foreach ($context->probes->prefixed('asset:') as $answer) {
            if (! $answer->ok()) {
                continue;
            }

            $control = $answer->header('cache-control');
            $age = $control !== null && preg_match('~max-age=(\d+)~i', $control, $match) === 1 ? (int) $match[1] : null;

            if ($control === null || (! str_contains($control, 'immutable') && ($age === null || $age < $minimum))) {
                $rows[] = ['url' => $answer->url, 'cache_control' => $control];
            }
        }

        if ($rows !== []) {
            yield $this->found('static-cache', ['count' => count($rows)], $context->base().'/', table: [
                'columns' => [Finding::column('url', 'url'), Finding::column('cache_control', 'missing')],
                'rows' => $rows,
            ]);
        }
    }
}
