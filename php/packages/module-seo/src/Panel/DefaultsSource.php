<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Seo\Rendering\SeoSource;
use WebxUi\Settings\Contacts\Channel;
use WebxUi\Settings\Contacts\Contacts;
use WebxUi\Settings\Contacts\Hours;
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
            $organisation['@id'] = self::organizationIdOf($home);
            $organisation['url'] = $home;
        }

        $logo = $this->url($settings->get('seo.org-logo', null, $locale));

        if ($logo !== null) {
            $organisation['logo'] = $logo;
        }

        $contacts = $this->container->make(Contacts::class);

        // The networks of the Contacts tab and the ones typed here before it existed, once each.
        $sameAs = array_values(array_unique([
            ...$this->sameAs($settings->get('seo.org-socials', null, $locale)),
            ...array_values(array_filter(array_map(
                static fn (Channel $social): string => $social->url,
                $contacts->socials($locale),
            ), static fn (string $url): bool => Contacts::isWebLink($url))),
        ]));

        if ($sameAs !== []) {
            $organisation['sameAs'] = $sameAs;
        }

        $organisation = [...$organisation, ...$this->contacts($contacts, $locale)];

        $blocks = [$organisation];

        if ($site !== null && $home !== '') {
            $blocks[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site, 'url' => $home];
        }

        return $blocks;
    }

    /**
     * What another block points at when it means this site's organisation — a `Service` naming
     * its `provider`, say — or null when there is no Organization block to point at.
     *
     * A reference rather than a copy of the fields: two copies of the name and the logo are two
     * things to keep in step, and a validator reads `{"@id": …}` as the block printed beside it.
     * Null when the block is not printed (no name, no address of the site), because a reference
     * to nothing is a warning in every validator.
     */
    public function organizationId(?string $locale = null): ?string
    {
        if (! $this->container->bound(Settings::class)) {
            return null;
        }

        $home = (string) $this->config->get('app.url', '');

        if ($home === '') {
            return null;
        }

        /** @var Settings $settings */
        $settings = $this->container->make(Settings::class);
        $name = $this->text($settings->get('seo.org-name', null, $locale))
            ?? $this->text($settings->get('general.project-name', null, $locale));

        return $name === null ? null : self::organizationIdOf($home);
    }

    /**
     * What the Contacts tab says about the organisation (WIDGETS §12.1): the main number in
     * E.164, the first e-mail, the main address; and where it is and when it is open, which
     * only a `LocalBusiness` may say — an Organization that is also a place, so the `@id` other
     * blocks point at is the same thing either way.
     *
     * @return array<string, mixed>
     */
    private function contacts(Contacts $contacts, ?string $locale): array
    {
        $fields = [];
        $phone = $contacts->primaryPhone($locale);
        $email = $contacts->emails($locale)[0] ?? null;
        $address = $contacts->primaryAddress($locale);
        $hours = $contacts->hours($locale);

        if ($phone !== null) {
            $fields['telephone'] = $phone->e164;
        }

        if ($email !== null) {
            $fields['email'] = $email->address;
        }

        if ($address !== null) {
            $fields['address'] = $address->text;
        }

        if ($address !== null && $address->hasCoordinates()) {
            $fields['@type'] = 'LocalBusiness';
            $fields['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $address->latitude, 'longitude' => $address->longitude];
        }

        if (! $hours->isEmpty()) {
            $fields['@type'] = 'LocalBusiness';
            $fields['openingHoursSpecification'] = $this->openingHours($hours);
        }

        return $fields;
    }

    /**
     * The week, a specification per group of days with the same interval, and the special dates
     * of the next two months, each valid on its one day. A day closed is 00:00–00:00, the way
     * Google reads "closed" in this markup.
     *
     * @return list<array<string, mixed>>
     */
    private function openingHours(Hours $hours): array
    {
        $names = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $time = static fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes % 1440, 60), $minutes % 60);
        $specifications = [];

        foreach ($hours->rows() as $row) {
            foreach ($row['intervals'] as [$opens, $closes]) {
                $specifications[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => array_map(static fn (int $day): string => 'https://schema.org/'.$names[$day], $row['days']),
                    'opens' => $time($opens),
                    // The whole day closes a minute before midnight: 00:00 would read as closed.
                    'closes' => $closes - $opens >= 1440 ? '23:59' : $time($closes),
                ];
            }
        }

        foreach ($hours->upcoming(null, 60) as $date => $exception) {
            foreach ($exception['intervals'] === [] ? [[0, 0]] : $exception['intervals'] as [$opens, $closes]) {
                $specifications[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'validFrom' => $date,
                    'validThrough' => $date,
                    'opens' => $time($opens),
                    'closes' => $time($closes),
                ];
            }
        }

        return $specifications;
    }

    private static function organizationIdOf(string $home): string
    {
        return rtrim($home, '/').'/#organization';
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

            // The field checks rows saved from now on; a row saved before it did, or written
            // straight into the table, must still not reach the page as `"sameAs": ["not a url"]`.
            if ($address !== null && filter_var($address, FILTER_VALIDATE_URL) !== false && preg_match('~^https?://~i', $address) === 1) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    /**
     * What a `wx-media` value resolves to — the field type has already worked out the address,
     * but as the library keeps it: `/storage/…`. A social network reads `og:image` and a
     * validator reads `logo` from somewhere else, where a path is no address at all.
     */
    private function url(mixed $value): ?string
    {
        $url = is_array($value) ? $this->text($value['url'] ?? null) : null;

        return $url !== null && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? Seo::root().$url : $url;
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
