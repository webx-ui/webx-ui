<?php

declare(strict_types=1);

namespace WebxUi\Team\Rendering;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Routing\Models\Route;
use WebxUi\Team\Models\Member;
use WebxUi\Team\Networks;

/**
 * A person as a template reads them — plain data, not the model (§5.2 of the team spec).
 *
 *     id, anchor, categories   what every element of a `wx-collection` carries; no categories here
 *     name, job_title          in the language asked for, else in the default one; '' when none
 *     initials                 what stands in for a missing photo
 *     text                     plain text in the language asked for, else '' (decision 8)
 *     photo                    what a `wx-media` field hands over: url, thumb, width, height, alt…
 *     socials                  network, label, url — in the editor's order, known networks only
 *     service_links            id, title, url of the services the person provides; [] without them
 *     fields                   the project's own fields (a patch on `team.form`), by name
 *
 * Every card is built from what was loaded with the list, so a list of any length is the same few
 * queries: the library once, the relations once, the addresses of the services once.
 */
final class Cards
{
    public function __construct(
        private readonly MediaFiles $files,
        private readonly MediaValues $media,
        private readonly Locales $locales,
        private readonly Networks $networks,
    ) {}

    /**
     * @param  list<Member>  $members
     * @return list<array<string, mixed>>
     */
    public function members(array $members, string $locale): array
    {
        // Every photo of the list in one query of the library rather than one per card.
        $this->files->load(array_values(array_filter(array_map(
            static fn (Member $member): ?string => $member->photoPath(),
            $members,
        ))));

        $this->loadServices($members);

        $default = $this->locales->defaultCode();
        $networks = $this->networks->all();

        return array_map(fn (Member $member): array => $this->member($member, $locale, $default, $networks), $members);
    }

    /**
     * @param  array<string, string>  $networks
     * @return array<string, mixed>
     */
    private function member(Member $member, string $locale, string $default, array $networks): array
    {
        $fields = [];

        foreach (array_keys((array) ($member->extraRaw() ?? [])) as $name) {
            $fields[(string) $name] = $member->extra((string) $name, $locale);
        }

        $name = $member->wordsIn('name', $locale, $default);

        return [
            'id' => (int) $member->getKey(),
            'anchor' => 'member-'.$member->getKey(),
            'categories' => [],
            'name' => $name,
            'initials' => Member::initials($name),
            'job_title' => $member->wordsIn('job_title', $locale, $default),
            'text' => $member->textIn($locale),
            'photo' => $this->photo($member, $locale),
            'socials' => $this->socials($member, $networks),
            'service_links' => $this->serviceLinks($member, $locale),
            'fields' => $fields,
        ];
    }

    /**
     * The links of the networks the site still has: one taken out of the config drops out here
     * and stays in the database, so putting it back brings the links back.
     *
     * @param  array<string, string>  $networks
     * @return list<array{network: string, label: string, url: string}>
     */
    private function socials(Member $member, array $networks): array
    {
        $links = [];

        foreach ($member->socialLinks() as $link) {
            if (! array_key_exists($link['network'], $networks)) {
                continue;
            }

            $links[] = ['network' => $link['network'], 'label' => $networks[$link['network']], 'url' => $link['url']];
        }

        return $links;
    }

    /**
     * The photo as the library knows it now, or null — also when its file was deleted from the
     * library: a card with an `<img>` that has no address is worse than one with the initials.
     *
     * @return array<string, mixed>|null
     */
    private function photo(Member $member, string $locale): ?array
    {
        if ($member->photoPath() === null) {
            return null;
        }

        $photo = $this->media->resolve($member->photo, $locale);

        return is_array($photo) && is_string($photo['url'] ?? null) ? $photo : null;
    }

    /**
     * The services of the whole list in one go, and their addresses with them. Copied from the
     * recipes' cards rather than shared: see "Итог T1" in the team spec — `module-admin` does not
     * know the address registry.
     *
     * @param  list<Member>  $members
     */
    private function loadServices(array $members): void
    {
        if ($members === []) {
            return;
        }

        Relations::load($members, Member::SERVICES);

        $services = [];

        foreach ($members as $member) {
            foreach ($member->related(Member::SERVICES) as $service) {
                $services[spl_object_id($service)] = $service;
            }
        }

        $services = array_values(array_filter($services, static fn (Model $service): bool => method_exists($service, 'routes')));

        if ($services !== []) {
            (new Collection($services))->loadMissing('routes');
        }
    }

    /** @return list<array{id: int, title: string, url: string}> */
    private function serviceLinks(Member $member, string $locale): array
    {
        $links = [];

        foreach ($member->related(Member::SERVICES, visible: true, locale: $locale) as $service) {
            if (method_exists($service, 'hasUrlIn') && ! $service->hasUrlIn($locale)) {
                continue;
            }

            $links[] = ['id' => (int) $service->getKey(), 'title' => $this->title($service, $locale), 'url' => $this->url($service, $locale)];
        }

        return $links;
    }

    /**
     * From the loaded registry rows when the list loaded them: `url()` would ask the registry again
     * for every card.
     */
    private function url(Model $entity, string $locale): string
    {
        if (! method_exists($entity, 'url') || ! method_exists($entity, 'urlOf')) {
            return '';
        }

        if (! $entity->relationLoaded('routes')) {
            return (string) $entity->url($locale);
        }

        /** @var \Illuminate\Support\Collection<int, Route> $routes */
        $routes = $entity->getRelation('routes');
        $row = $routes->first(
            static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL,
        );

        return $row instanceof Route ? (string) $entity->urlOf($row->path, $locale) : (string) $entity->url($locale);
    }

    private function title(Model $entity, string $locale): string
    {
        if (method_exists($entity, 'displayName')) {
            return (string) $entity->displayName($locale);
        }

        $value = method_exists($entity, 'getTranslation') ? $entity->getTranslation('title', $locale) : $entity->getAttribute('title');

        return is_string($value) && trim($value) !== '' ? $value : '#'.$entity->getKey();
    }
}
