<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A check that reads the snapshot page by page (§5.5): each HTML page that answered 200 is
 * handed to `inspect()`, and a finding is tied to its page so the page's card can list it.
 */
abstract class PageCheck extends Check
{
    protected const GROUP = 'page';

    /** @var list<string> */
    protected const NEEDS = ['crawl'];

    public function run(AuditContext $context): iterable
    {
        foreach ($this->pages($context)->lazyById(200) as $page) {
            yield from $this->inspect($page, $context);
        }
    }

    /**
     * @return iterable<Finding>
     */
    abstract protected function inspect(AuditPage $page, AuditContext $context): iterable;

    /**
     * The pages the check looks at — HTML that answered 200, unless it says otherwise.
     *
     * @return Builder<AuditPage>
     */
    protected function pages(AuditContext $context): Builder
    {
        return AuditPage::query()->where('run_id', $context->run->id)->html();
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @param  array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>}|null  $table
     */
    protected function on(AuditPage $page, string $summary, array $params = [], ?array $table = null, string $key = '', ?string $severity = null): Finding
    {
        $details = ['summary' => Finding::summary($summary, $params)];

        if ($table !== null) {
            $details['table'] = $table;
        }

        return new Finding(static::ID, $severity ?? static::SEVERITY, $page->url, $details, $key, $page->id);
    }
}
