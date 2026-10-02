<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\RobotsRules;
use WebxUi\Audit\Crawl\Urls;

/**
 * robots.txt closes the CSS or JS the home page loads: a search engine renders the page without
 * them and sees a different, often "not mobile-friendly", page.
 */
final class RobotsBlocksAssets extends Check
{
    protected const ID = 'robots.blocks_assets';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        $robots = $context->probes->get('robots');

        if ($robots === null || ! $robots->ok()) {
            return;
        }

        $rules = RobotsRules::parse($robots->body);
        $rows = [];

        foreach ($context->probes->assets as $asset) {
            $path = Urls::pathOf($asset);

            if (preg_match('~\.(css|js|mjs)(\?|$)~i', $path) === 1 && $rules->blocks($path)) {
                $rows[] = ['url' => $asset];
            }
        }

        if ($rows !== []) {
            yield $this->found('robots-blocks-assets', ['count' => count($rows)], $robots->url, table: [
                'columns' => [Finding::column('url', 'url')],
                'rows' => $rows,
            ]);
        }
    }
}
