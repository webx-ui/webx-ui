<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\RobotsRules;

/**
 * Lines of robots.txt a search engine skips: unknown directives, rules outside a group.
 */
final class RobotsSyntax extends Check
{
    protected const ID = 'robots.syntax';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        $robots = $context->probes->get('robots');

        if ($robots === null || ! $robots->ok()) {
            return;
        }

        $problems = RobotsRules::problems($robots->body);

        if ($problems !== []) {
            yield $this->found('robots-syntax', ['count' => count($problems)], $robots->url, table: [
                'columns' => [Finding::column('line'), Finding::column('value'), Finding::column('problem', 'word')],
                'rows' => array_slice($problems, 0, 50),
            ]);
        }
    }
}
