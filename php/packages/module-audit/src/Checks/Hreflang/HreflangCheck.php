<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hreflang;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Page\PageCheck;
use WebxUi\Audit\Runs\AuditPage;

/**
 * The language versions a page names (§5.5, `hreflang.*`): the alternates of each page are
 * looked up in the snapshot — the crawl puts every hreflang address in its queue, so the other
 * side has usually been asked too. An alternate the crawl had no room for is not judged.
 */
abstract class HreflangCheck extends PageCheck
{
    /** `x-default`, or a language with an optional script and region: `en`, `en-GB`, `zh-Hant-TW`. */
    private const CODE = '~^(x-default|[a-z]{2}(-[a-z]{4})?(-([a-z]{2}|[0-9]{3}))?)$~i';

    /** Codes that look right and are not: Japanese is `ja`, the United Kingdom is `GB`. */
    private const WRONG = ['~^jp(-|$)~i', '~-uk$~i'];

    /** @var array<string, AuditPage>|null */
    private ?array $snapshot = null;

    public function run(AuditContext $context): iterable
    {
        $this->snapshot = null;

        yield from parent::run($context);
    }

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->whereNotNull('hreflang')->where('hreflang', '<>', '[]');
    }

    /** The crawled page at that address, if the snapshot has it. */
    protected function target(AuditContext $context, string $url): ?AuditPage
    {
        if ($this->snapshot === null) {
            $this->snapshot = [];

            foreach (AuditPage::query()->where('run_id', $context->run->id)->whereNotNull('fetched_at')->get(['id', 'url', 'status', 'content_type', 'hreflang', 'indexable']) as $page) {
                $this->snapshot[$page->url] = $page;
            }
        }

        return $this->snapshot[$url] ?? null;
    }

    public static function validCode(string $code): bool
    {
        if (preg_match(self::CODE, $code) !== 1) {
            return false;
        }

        foreach (self::WRONG as $pattern) {
            if (preg_match($pattern, $code) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the page lists the address among its alternates.
     */
    protected static function names(AuditPage $page, string $url): bool
    {
        foreach ($page->hreflang ?? [] as $alternate) {
            if ($alternate['url'] === $url) {
                return true;
            }
        }

        return false;
    }
}
