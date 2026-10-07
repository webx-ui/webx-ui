<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Seo\Contracts\HasOpenGraph;
use WebxUi\Seo\Contracts\SharesImages;
use WebxUi\Seo\Rendering\Images\SharedImage;
use WebxUi\Settings\Settings;

/**
 * Open Graph, its `article:` properties and the Twitter lines, every one of them from what the
 * page already says — the merged title and description, the canonical, the picture the sources
 * chose, the entity's dates, the language — and none typed for the purpose.
 *
 * Kept apart from the resolve because it is only for a page being printed: the picture is
 * looked up in the library and maybe cut, the article asks for its rubric and its tags, and the
 * sitemap resolving ten thousand addresses needs none of that.
 *
 * Every group follows its switch in `webx-seo.print` (`og`, `article`, `twitter`), and a line
 * with nothing to say is not printed.
 *
 * @phpstan-type Tag array{attribute: 'property'|'name', key: string, content: string}
 */
final class SocialTags
{
    /**
     * Where a bare language code means one country more often than the code repeated does.
     * Anything else becomes `xx_XX`; a site that means another one says so in `webx-seo.og.locales`.
     */
    private const TERRITORIES = [
        'en' => 'en_US', 'uk' => 'uk_UA', 'ar' => 'ar_AR', 'cs' => 'cs_CZ', 'da' => 'da_DK',
        'el' => 'el_GR', 'et' => 'et_EE', 'fa' => 'fa_IR', 'he' => 'he_IL', 'hi' => 'hi_IN',
        'ja' => 'ja_JP', 'ka' => 'ka_GE', 'kk' => 'kk_KZ', 'ko' => 'ko_KR', 'ms' => 'ms_MY',
        'nb' => 'nb_NO', 'sl' => 'sl_SI', 'sq' => 'sq_AL', 'sr' => 'sr_RS', 'sv' => 'sv_SE',
        'vi' => 'vi_VN', 'zh' => 'zh_CN', 'be' => 'be_BY', 'hy' => 'hy_AM', 'uz' => 'uz_UZ',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly SharesImages $images,
    ) {}

    /**
     * @param  array<string, string>  $alternates  What `Alternates` gave the page: hreflang → address.
     * @return list<Tag>
     */
    public function for(SeoData $data, ?object $subject, string $locale, array $alternates = []): array
    {
        /** @var array<string, bool> $print */
        $print = (array) $this->config->get('webx-seo.print', []);
        $og = $data->og;
        [$source, $image] = $this->image($data);

        // The alt the source of this very picture gave; the page's title for one that gave none.
        $own = ($og['image'] ?? null) === $source ? ($og['image:alt'] ?? null) : null;
        $alt = $image === null ? null : ($own ?? $og['title'] ?? $data->title);

        // The picture's lines are worked out here from the one chosen, not taken from whichever
        // source happened to say them.
        foreach (array_keys($og) as $property) {
            if ($property === 'image' || str_starts_with($property, 'image:')) {
                unset($og[$property]);
            }
        }

        $tags = [];

        if ($print['og'] ?? true) {
            foreach (['title', 'description', 'url', 'type', 'site_name'] as $property) {
                $this->add($tags, 'property', 'og:'.$property, $og[$property] ?? null);
                unset($og[$property]);
            }

            $this->add($tags, 'property', 'og:locale', self::ogLocale($locale, $this->locales()));

            foreach (array_keys($alternates) as $hreflang) {
                $code = str_replace('-', '_', $hreflang);

                if ($hreflang !== 'x-default' && $code !== $locale) {
                    $this->add($tags, 'property', 'og:locale:alternate', self::ogLocale($code, $this->locales()));
                }
            }

            if ($image !== null) {
                $this->add($tags, 'property', 'og:image', $image->url);
                $this->add($tags, 'property', 'og:image:type', $image->type);
                $this->add($tags, 'property', 'og:image:width', $image->width === null ? null : (string) $image->width);
                $this->add($tags, 'property', 'og:image:height', $image->height === null ? null : (string) $image->height);
                $this->add($tags, 'property', 'og:image:alt', $alt);
            }

            // Whatever a source set beyond the standard set — a project's own source may know more.
            foreach ($og as $property => $content) {
                $this->add($tags, 'property', 'og:'.$property, $content);
            }
        }

        if (($print['article'] ?? true) && $subject instanceof HasOpenGraph && ($data->og['type'] ?? null) === $subject->openGraphType()) {
            foreach ($subject->openGraphProperties($locale) as $property => $content) {
                foreach (is_array($content) ? $content : [$content] as $one) {
                    $this->add($tags, 'property', $property, $one);
                }
            }
        }

        if ($print['twitter'] ?? true) {
            $this->add($tags, 'name', 'twitter:card', $image === null ? 'summary' : 'summary_large_image');
            $this->add($tags, 'name', 'twitter:site', $this->twitterHandle($locale));
            $this->add($tags, 'name', 'twitter:title', $data->og['title'] ?? $data->title);
            $this->add($tags, 'name', 'twitter:description', $data->og['description'] ?? $data->description);
            $this->add($tags, 'name', 'twitter:image', $image?->url);
            $this->add($tags, 'name', 'twitter:image:alt', $alt);
        }

        return $tags;
    }

    /**
     * `en` → `en_US`, `pt-BR` → `pt_BR`, `de` → `de_DE`: the `language_TERRITORY` Open Graph wants.
     *
     * @param  array<string, string>  $configured
     */
    public static function ogLocale(string $code, array $configured = []): ?string
    {
        if (isset($configured[$code])) {
            return $configured[$code];
        }

        $parts = preg_split('/[-_]/', trim($code)) ?: [];
        $language = strtolower($parts[0] ?? '');

        if (preg_match('/^[a-z]{2,3}$/', $language) !== 1) {
            return null;
        }

        $territory = strtoupper($parts[1] ?? '');

        if (preg_match('/^[A-Z]{2}$/', $territory) === 1) {
            return $language.'_'.$territory;
        }

        return self::TERRITORIES[$language] ?? $language.'_'.strtoupper($language);
    }

    /**
     * The first picture of the sources, highest first, that a network can show — an SVG logo on a
     * press outlet's page gives way to the site's default image. The address the source gave
     * comes back beside it: what is printed may be a variant cut from it.
     *
     * @return array{0: string|null, 1: SharedImage|null}
     */
    private function image(SeoData $data): array
    {
        foreach ($data->images as $url) {
            $image = $this->images->share($url);

            if ($image !== null) {
                return [$url, $image];
            }
        }

        return [null, null];
    }

    /**
     * `@handle` of the X profile among the organisation's profiles (`seo.org-socials`), when there
     * is one. The site's account, not an author's: there is no field for authors, and guessing is
     * how the wrong account gets the credit.
     */
    private function twitterHandle(string $locale): ?string
    {
        if (! app()->bound(Settings::class)) {
            return null;
        }

        $rows = app(Settings::class)->get('seo.org-socials', null, $locale);

        foreach (is_array($rows) ? $rows : [] as $row) {
            $url = is_array($row) ? ($row['url'] ?? null) : $row;
            $host = is_string($url) ? strtolower((string) parse_url(trim($url), PHP_URL_HOST)) : '';

            if (! in_array(preg_replace('/^(www\.|mobile\.)/', '', $host), ['x.com', 'twitter.com'], true)) {
                continue;
            }

            $handle = explode('/', trim((string) parse_url(trim((string) $url), PHP_URL_PATH), '/'))[0];

            if (preg_match('/^@?([A-Za-z0-9_]{1,15})$/', $handle, $match) === 1 && ! in_array(strtolower($match[1]), ['home', 'share', 'intent', 'i', 'search'], true)) {
                return '@'.$match[1];
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function locales(): array
    {
        /** @var array<string, string> $locales */
        $locales = (array) $this->config->get('webx-seo.og.locales', []);

        return $locales;
    }

    /**
     * @param  list<Tag>  $tags
     * @param  'property'|'name'  $attribute
     */
    private function add(array &$tags, string $attribute, string $key, ?string $content): void
    {
        $content = $content === null ? '' : trim($content);

        if ($content !== '') {
            $tags[] = ['attribute' => $attribute, 'key' => $key, 'content' => $content];
        }
    }
}
