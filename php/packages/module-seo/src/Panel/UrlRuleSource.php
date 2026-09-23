<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;

/**
 * The rules an editor wrote for addresses.
 *
 * Highest of the sources that ship with the module: somebody sat down and said what this page
 * should look like, which beats anything worked out from defaults.
 */
final class UrlRuleSource implements SeoSource
{
    public function __construct(
        private readonly SeoRules $rules,
        private readonly UrlMatcher $matcher,
        private readonly Config $config,
    ) {}

    public function priority(): int
    {
        return (int) $this->config->get('webx-seo.sources.urls', 100);
    }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        $rule = $this->matching($url);

        return $rule?->toSeoData($locale);
    }

    /**
     * Is there an active rule for this address at all?
     *
     * Asked by a module whose page is out of the index unless somebody asked for it — the tag
     * pages of `webx-ui/module-blog` are the first (§12 of its spec). The fact that a rule
     * matches is the answer, not what the rule says: a rule filling in a title and nothing else
     * still means "this page is wanted", and a merge of fields could never see that, because a
     * rule with an empty `robots` leaves the `noindex` of the source below it in place.
     *
     * Over the same compiled list {@see UrlMatcher} works on, and deliberately not over a
     * matcher of the asker's own: two ideas of what `*` means against `**` drift apart on the
     * first star, and they drift apart silently.
     */
    public function hasRuleFor(string $url): bool
    {
        return $this->matcher->match($url, $this->rules->urls()) !== null;
    }

    /** The rule that won, as a record — what `test-url` shows and the middleware never needs. */
    public function matching(string $url): ?SeoUrl
    {
        $row = $this->matcher->match($url, $this->rules->urls());
        $id = $row === null ? null : ($row['id'] ?? null);

        return is_numeric($id) ? SeoUrl::query()->find((int) $id) : null;
    }
}
