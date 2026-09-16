<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Seo\HasSeo;

/**
 * What the entity on this page says about itself.
 *
 * Between the two that shipped first, and that is the whole argument for the number: a rule an
 * editor wrote for an address (`100`) was written *because* the page was wrong, so it has to
 * win; the defaults (`10`) are what the site says when nobody said anything, so they have to
 * lose. A page's own fields are the ordinary case in between.
 *
 * The subject is whatever the template named or the address registry resolved — this source
 * never looks a page up by its address. Two reasons: the same entity can be reachable at more
 * than one address, and `POST /test-url` asks about somebody else's page, where guessing an
 * entity would answer a question nobody asked.
 */
final class EntitySource implements SeoSource
{
    public function __construct(private readonly Config $config) {}

    public function priority(): int
    {
        return (int) $this->config->get('webx-seo.sources.entities', 50);
    }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        if ($subject === null || ! in_array(HasSeo::class, class_uses_recursive($subject), true)) {
            return null;
        }

        /** @var object{seoData: callable} $subject */
        return $subject->seoData($locale);
    }
}
