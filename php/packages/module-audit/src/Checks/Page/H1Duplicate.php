<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * The same first H1 on several indexable pages, letter case and spaces aside. The H1 is a JSON
 * list in the snapshot, so the pages are grouped here rather than by SQL like
 * {@see DuplicateCheck}; the finding has the same shape.
 */
final class H1Duplicate extends Check
{
    protected const ID = 'h1.duplicate';

    protected const GROUP = 'page';

    protected const SEVERITY = Severity::WARNING;

    /** @var list<string> */
    protected const NEEDS = ['crawl'];

    private const OTHERS = 20;

    public function run(AuditContext $context): iterable
    {
        /** @var array<string, list<AuditPage>> $groups */
        $groups = [];

        $pages = AuditPage::query()
            ->where('run_id', $context->run->id)
            ->where('indexable', true)
            ->whereNotNull('h1')
            ->orderBy('id')
            ->select(['id', 'url', 'title', 'h1']);

        foreach ($pages->lazyById(500) as $page) {
            $first = $page->h1[0] ?? '';
            $key = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $first)));

            if ($key !== '') {
                $groups[$key][] = $page;
            }
        }

        foreach ($groups as $same) {
            if (count($same) < 2) {
                continue;
            }

            foreach ($same as $page) {
                $others = array_values(array_filter($same, static fn (AuditPage $other): bool => $other->id !== $page->id));

                yield new Finding(self::ID, self::SEVERITY, $page->url, [
                    'summary' => Finding::summary('h1-duplicate', [
                        'count' => count($others),
                        'value' => mb_substr((string) ($page->h1[0] ?? ''), 0, 120),
                    ]),
                    'table' => [
                        'columns' => [Finding::column('url', 'url'), Finding::column('title')],
                        'rows' => array_map(
                            static fn (AuditPage $other): array => ['url' => $other->url, 'title' => $other->title],
                            array_slice($others, 0, self::OTHERS),
                        ),
                    ],
                ], '', $page->id);
            }
        }
    }
}
