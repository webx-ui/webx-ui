<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A check over the addresses pages point at: the links that match `links()`, one finding per
 * page that has them, every such address of that page in its table.
 */
abstract class LinkCheck extends Check
{
    protected const GROUP = 'links';

    /** @var list<string> */
    protected const NEEDS = ['crawl'];

    /** The most addresses a finding lists — the count in the summary is the whole number. */
    protected const ROWS = 50;

    /** The summary line, with `:count`. */
    protected const SUMMARY = '';

    /** What of the linked page a row shows. */
    protected const TO = 'to:id,status';

    public function run(AuditContext $context): iterable
    {
        $page = null;
        $rows = [];
        $count = 0;

        $links = $this->links($context)
            ->with(static::TO)
            ->orderBy('from_page_id')
            ->orderBy('id');

        foreach ($links->lazy(500) as $link) {
            if ($page !== null && $page->id !== $link->from_page_id) {
                yield $this->finding($page, $rows, $count);
                $page = null;
            }

            if ($page === null) {
                $page = AuditPage::query()->find($link->from_page_id, ['id', 'url']);
                $rows = [];
                $count = 0;
            }

            $count++;

            if (count($rows) < static::ROWS) {
                $rows[] = $this->row($link);
            }
        }

        if ($page !== null) {
            yield $this->finding($page, $rows, $count);
        }
    }

    /**
     * The links that are a problem.
     *
     * @return Builder<AuditLink>
     */
    abstract protected function links(AuditContext $context): Builder;

    /**
     * @return Builder<AuditLink>
     */
    protected function query(AuditContext $context): Builder
    {
        return AuditLink::query()->where('run_id', $context->run->id);
    }

    /**
     * @return list<array{key: string, label: string, type: string}>
     */
    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('kind'), Finding::column('anchor')];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(AuditLink $link): array
    {
        return ['url' => $link->to_url, 'kind' => $link->kind, 'anchor' => $link->anchor, 'status' => $link->to?->status];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function finding(AuditPage $page, array $rows, int $count): Finding
    {
        return new Finding(static::ID, static::SEVERITY, $page->url, [
            'summary' => Finding::summary(static::SUMMARY, ['count' => $count]),
            'table' => ['columns' => $this->columns(), 'rows' => $rows],
        ], '', $page->id);
    }
}
