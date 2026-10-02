<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hosts;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A check about a host rather than a page: one finding per host that `hosts()` picks, keyed by
 * the host, with the pages that link to it in its table.
 */
abstract class HostCheck extends Check
{
    protected const GROUP = 'hosts';

    /** @var list<string> */
    protected const NEEDS = ['crawl'];

    /** The summary line, with `:host` and `:count`. */
    protected const SUMMARY = '';

    private const PAGES = 20;

    public function run(AuditContext $context): iterable
    {
        $external = AuditLink::query()
            ->where('run_id', $context->run->id)
            ->whereNotNull('host')
            ->where('host_class', '<>', 'own');

        $hosts = (clone $external)->distinct()->pluck('host')->map(static fn (mixed $host): string => (string) $host)->all();

        foreach ($this->hosts($context, $hosts) as $host) {
            $links = (clone $external)->where('host', $host);
            $pageIds = (clone $links)->distinct()->pluck('from_page_id')->all();
            $first = (clone $links)->orderBy('id')->value('to_url');

            yield new Finding(static::ID, static::SEVERITY, is_string($first) ? $first : null, [
                'summary' => Finding::summary(static::SUMMARY, ['host' => $host, 'count' => count($pageIds)]),
                'table' => [
                    'columns' => [Finding::column('page', 'url'), Finding::column('url', 'url'), Finding::column('anchor')],
                    'rows' => $this->rows($links, self::PAGES),
                ],
            ], $host);
        }
    }

    /**
     * The hosts among the run's outgoing ones that this check is about.
     *
     * @param  list<string>  $hosts
     * @return list<string>
     */
    abstract protected function hosts(AuditContext $context, array $hosts): array;

    /**
     * @param  Builder<AuditLink>  $links
     * @return list<array<string, mixed>>
     */
    private function rows(Builder $links, int $limit): array
    {
        $rows = [];

        foreach ((clone $links)->orderBy('from_page_id')->limit($limit)->get(['from_page_id', 'to_url', 'anchor']) as $link) {
            $rows[] = [
                'page' => AuditPage::query()->whereKey($link->from_page_id)->value('url'),
                'url' => $link->to_url,
                'anchor' => $link->anchor,
            ];
        }

        return $rows;
    }
}
