<?php

declare(strict_types=1);

namespace WebxUi\Blog\Seo;

use WebxUi\Blog\Models\Tag;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;

/**
 * What a tag page says about itself (§12).
 *
 * Below `EntitySource` at 50, because a tag has no SEO card and this is not one: it is the
 * module answering for a page nobody wrote anything about. Above the site defaults at 10,
 * because the name of the tag is a better title than "Blog".
 *
 * The `robots` half is the interesting one, and it is deliberately not a merge of fields.
 * `UrlRuleSource` sits at 100 and beats everything, but {@see SeoData::mergeOver()} works field
 * by field: a rule that fills in a title and a description and leaves `robots` empty would leave
 * *our* `noindex` standing underneath it, and the editor who wrote that rule would never find
 * out. So what decides is the fact that a rule matches at all, asked of the module that owns the
 * rules — see {@see TagIndexing}.
 */
final class TagSource implements SeoSource
{
    /**
     * Under the entity's own card, over the site's defaults.
     *
     * Not configurable, unlike the three that ship with `module-seo`. Those numbers are a site's
     * business because a site may genuinely want its defaults to beat a page; where this one
     * sits is a fact about how it works with the rules above it, and moving it would only break
     * that.
     */
    private const PRIORITY = 40;

    public function __construct(private readonly TagIndexing $indexing) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        if (! $subject instanceof Tag) {
            return null;
        }

        $title = (string) $subject->getTranslation('title', $locale);

        // The address as the request had it, so that a rule written for `/blog/tag/belts` is
        // compared against the same string the rule engine compares it against.
        $robots = $this->indexing->robots($subject, $url);

        if ($title === '' && $robots === null) {
            return null;
        }

        return SeoData::make([
            'title' => $title === '' ? null : $title,
            'robots' => $robots,
        ]);
    }
}
