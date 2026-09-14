<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;
use WebxUi\Settings\Settings;

/**
 * What the site says about itself when nothing more specific has been written: the fallback
 * picture for social networks, and the schema.org blocks that describe the organisation and
 * the website.
 *
 * Lowest priority, and only fields — the merge is per field, so a rule that sets nothing but a
 * title still gets its picture and its Organization block from here.
 *
 * Markup about a page's *contents* deliberately does not come from settings. `Article`,
 * `Product`, `BreadcrumbList` are generated from the entity, later, by whoever owns it: typed
 * in by hand they drift away from the page within a month and start lying to search engines.
 */
final class DefaultsSource implements SeoSource
{
    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    public function priority(): int
    {
        return (int) $this->config->get('webx-seo.sources.defaults', 10);
    }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        if (! $this->container->bound(Settings::class)) {
            return null;
        }

        /** @var Settings $settings */
        $settings = $this->container->make(Settings::class);

        $site = $this->text($settings->get('general.project-name', null, $locale));
        $og = [];

        $image = $this->url($settings->get('seo.default-og', null, $locale));

        if ($image !== null) {
            $og['image'] = $image;
        }

        if ($site !== null) {
            $og['site_name'] = $site;
        }

        $data = SeoData::make([
            'og' => $og,
            'jsonLd' => $this->jsonLd($settings, $site, $locale),
        ]);

        return $data->isEmpty() ? null : $data;
    }

    /**
     * Organization and WebSite, built from the fields rather than typed as JSON. These two are
     * about the site, they are the same on every page, and nobody should have to keep a block
     * of JSON-LD in their head to change a logo.
     *
     * @return list<array<string, mixed>>
     */
    private function jsonLd(Settings $settings, ?string $site, ?string $locale): array
    {
        $name = $this->text($settings->get('seo.org-name', null, $locale)) ?? $site;

        if ($name === null) {
            return [];
        }

        $home = (string) $this->config->get('app.url', '');
        $organisation = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $name];

        if ($home !== '') {
            $organisation['url'] = $home;
        }

        $logo = $this->url($settings->get('seo.org-logo', null, $locale));

        if ($logo !== null) {
            $organisation['logo'] = $logo;
        }

        $sameAs = $this->sameAs($settings->get('seo.org-socials', null, $locale));

        if ($sameAs !== []) {
            $organisation['sameAs'] = $sameAs;
        }

        $blocks = [$organisation];

        if ($site !== null && $home !== '') {
            $blocks[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site, 'url' => $home];
        }

        return $blocks;
    }

    /**
     * The repeater's rows, each one address.
     *
     * @return list<string>
     */
    private function sameAs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $addresses = [];

        foreach ($value as $row) {
            $address = $this->text(is_array($row) ? ($row['url'] ?? null) : $row);

            if ($address !== null) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    /** What a `wx-media` value resolves to — the field type has already worked out the address. */
    private function url(mixed $value): ?string
    {
        return is_array($value) ? $this->text($value['url'] ?? null) : null;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : $text;
    }
}
