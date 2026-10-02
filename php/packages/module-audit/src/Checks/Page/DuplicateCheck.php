<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Runs\AuditPage;

/**
 * One value on several indexable pages — a title, a description, a text (`*.duplicate`, §4).
 * Each page gets its own finding with the others listed, so fixing one page clears one finding
 * and the rest stay until they are fixed too.
 */
abstract class DuplicateCheck extends Check
{
    protected const GROUP = 'page';

    /** @var list<string> */
    protected const NEEDS = ['crawl'];

    /** The column of `audit_pages` compared. */
    protected const COLUMN = '';

    /** The summary line, with `:count` and `:value`. */
    protected const SUMMARY = '';

    /** The most other pages a finding lists. */
    private const OTHERS = 20;

    public function run(AuditContext $context): iterable
    {
        $pages = AuditPage::query()
            ->where('run_id', $context->run->id)
            ->where('indexable', true)
            ->whereNotNull(static::COLUMN)
            ->where(static::COLUMN, '<>', '');

        $values = (clone $pages)
            ->select(static::COLUMN)
            ->groupBy(static::COLUMN)
            ->havingRaw('count(*) > 1')
            ->pluck(static::COLUMN);

        foreach ($values as $value) {
            /** @var list<AuditPage> $same */
            $same = (clone $pages)->where(static::COLUMN, $value)->orderBy('id')->get(['id', 'url', 'title'])->all();

            foreach ($same as $page) {
                $others = array_values(array_filter($same, static fn (AuditPage $other): bool => $other->id !== $page->id));

                yield new Finding(static::ID, static::SEVERITY, $page->url, [
                    'summary' => Finding::summary(static::SUMMARY, [
                        'count' => count($others),
                        'value' => mb_substr((string) $value, 0, 120),
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
