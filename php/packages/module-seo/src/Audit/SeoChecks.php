<?php

declare(strict_types=1);

namespace WebxUi\Seo\Audit;

use Illuminate\Support\Collection;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Contracts\AuditCheck;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * The checks SEO brings to the audit (§7): what is wrong in its own tables. Two look only at the
 * tables, before any crawl; two compare an exact row with the page the crawl found at its
 * address. One class, four checks — each is a query and a loop, and a class per check would be
 * four files of bookkeeping around them.
 */
final readonly class SeoChecks implements AuditCheck
{
    public const REDIRECT_CHAIN = 'seo.redirect_chain';

    public const TITLE_DUPLICATE = 'seo.title_duplicate';

    public const REDIRECT_BROKEN = 'seo.redirect_broken';

    public const RULE_DEAD = 'seo.rule_dead';

    /** id => [severity, needs] */
    public const CHECKS = [
        self::REDIRECT_CHAIN => [Severity::WARNING, ['database']],
        self::TITLE_DUPLICATE => [Severity::WARNING, ['database']],
        self::REDIRECT_BROKEN => [Severity::ERROR, ['crawl']],
        self::RULE_DEAD => [Severity::NOTICE, ['crawl']],
    ];

    public function __construct(
        private string $id,
        private RedirectChains $chains,
    ) {}

    public function textNamespace(): string
    {
        return 'webx-seo';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function group(): string
    {
        return 'seo';
    }

    public function severity(): string
    {
        return self::CHECKS[$this->id][0];
    }

    public function needs(): array
    {
        return self::CHECKS[$this->id][1];
    }

    public function run(AuditContext $context): iterable
    {
        return match ($this->id) {
            self::REDIRECT_CHAIN => $this->chains($context),
            self::TITLE_DUPLICATE => $this->titles(),
            self::REDIRECT_BROKEN => $this->brokenRedirects($context),
            default => $this->deadRules($context),
        };
    }

    /**
     * A redirect whose target is itself redirected — found at the head of the chain only, so a
     * chain of four is one finding and not three.
     *
     * @return iterable<Finding>
     */
    private function chains(AuditContext $context): iterable
    {
        $exact = SeoRedirect::query()->active()->where('match_type', UrlMatcher::EXACT)->orderBy('id')->get();
        $targets = $exact->map(static fn (SeoRedirect $row): string => UrlNormaliser::normalise($row->target))->flip();

        foreach ($exact as $row) {
            $from = UrlNormaliser::normalise($row->pattern);

            if ($targets->has($from)) {
                continue;
            }

            $walk = $this->chains->walk($from);

            if (count($walk['hops']) < 2) {
                continue;
            }

            $rows = array_map(static fn (array $hop): array => ['url' => $hop['from'], 'location' => $hop['to']], $walk['hops']);

            yield $this->finding('redirect-chain', ['steps' => count($walk['hops'])], [
                'columns' => [Finding::column('url'), Finding::column('location')],
                'rows' => $rows,
            ], $context->base().$from, (string) $row->id);
        }
    }

    /**
     * The same title written in the cards of several entities, per language: two pages that say
     * they are the same page.
     *
     * @return iterable<Finding>
     */
    private function titles(): iterable
    {
        /** @var array<string, list<SeoMeta>> $byTitle */
        $byTitle = [];

        foreach (SeoMeta::query()->lazyById(500) as $meta) {
            $raw = json_decode((string) $meta->getRawOriginal('title'), true);

            foreach (is_array($raw) ? $raw : [] as $locale => $title) {
                if (is_string($title) && trim($title) !== '') {
                    $byTitle[$locale."\n".mb_strtolower(trim($title))][] = $meta;
                }
            }
        }

        foreach ($byTitle as $key => $metas) {
            if (count($metas) < 2) {
                continue;
            }

            [$locale, $title] = explode("\n", $key, 2);

            yield $this->finding('title-duplicate', ['title' => $title, 'count' => count($metas)], [
                'columns' => [Finding::column('record'), Finding::column('locale')],
                'rows' => (new Collection($metas))->take(20)->map(static fn (SeoMeta $meta): array => [
                    'record' => class_basename($meta->seoable_type).' #'.$meta->seoable_id,
                    'locale' => $locale,
                ])->values()->all(),
            ], null, sha1($key));
        }
    }

    /**
     * An exact redirect that sends visitors to a page the crawl found broken.
     *
     * @return iterable<Finding>
     */
    private function brokenRedirects(AuditContext $context): iterable
    {
        foreach (SeoRedirect::query()->active()->where('match_type', UrlMatcher::EXACT)->orderBy('id')->get() as $row) {
            if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $row->target) === 1) {
                continue;
            }

            $page = $this->snapshot($context, $row->target);

            if ($page !== null && ($page->status ?? 0) >= 400) {
                yield $this->finding('redirect-broken', ['target' => $row->target, 'status' => (int) $page->status], [
                    'columns' => [Finding::column('url'), Finding::column('location', 'url'), Finding::column('status', 'status')],
                    'rows' => [['url' => $row->pattern, 'location' => $page->url, 'status' => $page->status]],
                ], $context->base().UrlNormaliser::normalise($row->pattern), (string) $row->id);
            }
        }
    }

    /**
     * An exact rule for an address the crawl found gone — the rule is for nobody.
     *
     * @return iterable<Finding>
     */
    private function deadRules(AuditContext $context): iterable
    {
        foreach (SeoUrl::query()->active()->where('match_type', UrlMatcher::EXACT)->orderBy('id')->get() as $rule) {
            $page = $this->snapshot($context, $rule->pattern);

            if ($page !== null && in_array($page->status, [404, 410], true)) {
                yield $this->finding('rule-dead', ['status' => (int) $page->status], null, $page->url, (string) $rule->id);
            }
        }
    }

    private function snapshot(AuditContext $context, string $path): ?AuditPage
    {
        $url = $context->base().UrlNormaliser::normalise($path);

        return AuditPage::query()->where('run_id', $context->run->id)->where('url_hash', AuditPage::hash($url))->first();
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @param  array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>}|null  $table
     */
    private function finding(string $summary, array $params, ?array $table, ?string $url, string $key): Finding
    {
        $details = ['summary' => ['key' => 'webx-seo::audit.'.$summary, 'params' => $params]];

        if ($table !== null) {
            $details['table'] = $table;
        }

        return new Finding($this->id, $this->severity(), $url, $details, $key);
    }
}
