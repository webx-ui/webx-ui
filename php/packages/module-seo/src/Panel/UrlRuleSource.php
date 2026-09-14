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

    /** The rule that won, as a record — what `test-url` shows and the middleware never needs. */
    public function matching(string $url): ?SeoUrl
    {
        $row = $this->matcher->match($url, $this->rules->urls());
        $id = $row === null ? null : ($row['id'] ?? null);

        return is_numeric($id) ? SeoUrl::query()->find((int) $id) : null;
    }
}
