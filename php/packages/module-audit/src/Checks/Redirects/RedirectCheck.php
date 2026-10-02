<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Redirects;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Runs\AuditPage;

/**
 * The redirects of the snapshot as chains (§5.4): every step was crawled as a page of its own,
 * so a chain is followed through `redirect_to` without asking the site again. A chain ends on an
 * answer that is not a redirect, on an address outside the snapshot, or on a step it has
 * already been through — a loop.
 *
 * The head of a chain is a redirect no other redirect leads to: `/old` → `/new` → `/new/` is one
 * chain of two steps, said once, at `/old`.
 */
abstract class RedirectCheck extends Check
{
    protected const GROUP = 'redirects';

    /** @var list<string> */
    protected const NEEDS = ['crawl'];

    /** Steps followed at most — a chain longer than this is a problem whatever its end. */
    private const STEPS = 10;

    public function run(AuditContext $context): iterable
    {
        /** @var array<string, array{id: int, url: string, status: int|null, redirect_to: string|null}> $byUrl */
        $byUrl = [];

        foreach (AuditPage::query()->where('run_id', $context->run->id)->whereNotNull('fetched_at')->get(['id', 'url', 'status', 'redirect_to']) as $page) {
            $byUrl[$page->url] = ['id' => $page->id, 'url' => $page->url, 'status' => $page->status, 'redirect_to' => $page->redirect_to];
        }

        $targets = [];

        foreach ($byUrl as $page) {
            if ($page['redirect_to'] !== null) {
                $targets[$page['redirect_to']] = true;
            }
        }

        foreach ($byUrl as $page) {
            if ($page['redirect_to'] === null) {
                continue;
            }

            yield from $this->inspect($page, $this->chain($page, $byUrl), ! isset($targets[$page['url']]));
        }
    }

    /**
     * @param  array{id: int, url: string, status: int|null, redirect_to: string|null}  $page
     * @param  array{steps: list<array{url: string, status: int|null}>, loop: bool, end: array{url: string, status: int|null}|null}  $chain
     * @return iterable<Finding>
     */
    abstract protected function inspect(array $page, array $chain, bool $head): iterable;

    /**
     * The steps from a redirect to where it ends: `steps` are the redirects themselves, `end` is
     * the answer they arrive at (null when the chain leaves the snapshot or loops).
     *
     * @param  array{id: int, url: string, status: int|null, redirect_to: string|null}  $page
     * @param  array<string, array{id: int, url: string, status: int|null, redirect_to: string|null}>  $byUrl
     * @return array{steps: list<array{url: string, status: int|null}>, loop: bool, end: array{url: string, status: int|null}|null}
     */
    private function chain(array $page, array $byUrl): array
    {
        $steps = [];
        $seen = [];
        $current = $page;

        while ($current['redirect_to'] !== null && count($steps) < self::STEPS) {
            if (isset($seen[$current['url']])) {
                return ['steps' => $steps, 'loop' => true, 'end' => null];
            }

            $seen[$current['url']] = true;
            $steps[] = ['url' => $current['url'], 'status' => $current['status']];
            $next = $byUrl[$current['redirect_to']] ?? null;

            if ($next === null) {
                return ['steps' => $steps, 'loop' => false, 'end' => null];
            }

            $current = $next;
        }

        if ($current['redirect_to'] !== null) {
            return ['steps' => $steps, 'loop' => isset($seen[$current['url']]), 'end' => null];
        }

        return ['steps' => $steps, 'loop' => false, 'end' => ['url' => $current['url'], 'status' => $current['status']]];
    }

    /**
     * The chain as the expansion shows it: every step with its code, then where it ends.
     *
     * @param  array{steps: list<array{url: string, status: int|null}>, loop: bool, end: array{url: string, status: int|null}|null}  $chain
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>}
     */
    protected function table(array $chain): array
    {
        return [
            'columns' => [Finding::column('url', 'url'), Finding::column('status', 'status')],
            'rows' => [...$chain['steps'], ...($chain['end'] === null ? [] : [$chain['end']])],
        ];
    }

    /**
     * @param  array{id: int, url: string, status: int|null, redirect_to: string|null}  $page
     * @param  array<string, scalar|null>  $params
     * @param  array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>}|null  $table
     */
    protected function at(array $page, string $summary, array $params = [], ?array $table = null): Finding
    {
        $details = ['summary' => Finding::summary($summary, $params)];

        if ($table !== null) {
            $details['table'] = $table;
        }

        return new Finding(static::ID, static::SEVERITY, $page['url'], $details, '', $page['id']);
    }
}
