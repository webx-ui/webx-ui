<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A check of who links to a page (`structure.*`, the incoming side): for every indexable page
 * other than a home, how many pages link to it, how many of those search engines may index, and
 * how many link without `nofollow` — counted in one pass over `audit_links` when the check
 * starts. A page's links to itself do not count; a page that has none is `structure.orphan`'s.
 */
abstract class IncomingCheck extends PageCheck
{
    protected const GROUP = 'structure';

    /** @var array<int, array{pages: int, indexable: int, followed: int}> */
    private array $incoming = [];

    public function run(AuditContext $context): iterable
    {
        $this->incoming = self::count($context->run->id);

        yield from parent::run($context);

        $this->incoming = [];
    }

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)
            ->where('indexable', true)
            ->where('links_in', '>', 0)
            ->where('source', '<>', AuditPage::HOME);
    }

    /**
     * @return array{pages: int, indexable: int, followed: int}
     */
    protected function incoming(AuditPage $page): array
    {
        return $this->incoming[$page->id] ?? ['pages' => 0, 'indexable' => 0, 'followed' => 0];
    }

    /**
     * @return array<int, array{pages: int, indexable: int, followed: int}>
     */
    private static function count(int $run): array
    {
        $indexable = AuditPage::query()->where('run_id', $run)->where('indexable', true)->pluck('id')->flip()->all();

        /** @var array<int, array<int, bool>> $from */
        $from = [];

        AuditLink::query()
            ->where('run_id', $run)
            ->where('kind', AuditLink::A)
            ->whereNotNull('to_page_id')
            ->whereColumn('to_page_id', '<>', 'from_page_id')
            ->select(['id', 'from_page_id', 'to_page_id', 'rel'])
            ->lazyById(2000)
            ->each(static function (AuditLink $link) use (&$from): void {
                $followed = preg_match('~\bnofollow\b~i', (string) $link->rel) !== 1;
                $to = (int) $link->to_page_id;
                // A page that links both plainly and with nofollow still passes weight.
                $from[$to][$link->from_page_id] = ($from[$to][$link->from_page_id] ?? false) || $followed;
            });

        $counts = [];

        foreach ($from as $to => $sources) {
            $counts[$to] = [
                'pages' => count($sources),
                'indexable' => count(array_intersect_key($sources, $indexable)),
                'followed' => count(array_filter($sources)),
            ];
        }

        return $counts;
    }
}
