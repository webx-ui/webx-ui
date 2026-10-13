<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\SiteBar\EntityLink;
use WebxUi\Admin\SiteBar\SiteBar;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Rendering\Seo;

/**
 * The SEO tab of the panel on the site (WEBX_UI_SITE_BAR.md, §7): what a page says about
 * itself, where each line of it came from, and where an edit made beside the page would land.
 *
 * The question the tab answers is «why does this page have that title», so every field comes
 * with its origin — the step of `Seo::chain()` that won it — and, before anything is saved, with
 * the one thing that makes an edit pointless: a source above the place the edit goes to that
 * already says something for that field.
 *
 * Where an edit goes is decided here, once, for the GET and the screen alike:
 *
 * - **the record** behind the address, when it has a card (`HasSeo`) and the administrator may
 *   edit it — the same right its form's card is saved with;
 * - **a rule for this exact address** otherwise, with `seo.manage` — a rule works for any
 *   address, which is why a page of the site's own code gets the tab at all.
 *
 * Somebody who may only look at SEO gets no tab: the panel's test-url answers the same
 * question, and a tab with nothing to change in it is a trace on the page and no help.
 */
final class SiteBarSeo
{
    /** The fields the tab edits; everything else is a link into the panel. */
    public const FIELDS = ['title', 'description', 'h1', 'robots'];

    public function __construct(
        private readonly Seo $seo,
        private readonly UrlRuleSource $rules,
        private readonly Resolver $resolver,
        private readonly RouteTypes $types,
        private readonly SiteBar $bar,
        private readonly Locales $locales,
        private readonly Config $config,
    ) {}

    /**
     * Whether the tab is there for this administrator on this page — the section the bar asks.
     *
     * A rule needs no record, so `seo.manage` is enough anywhere; a record's own card needs the
     * right of its section, and nothing about SEO.
     */
    public static function available(HasPermissions $user, ?Model $entity, ?EntityLink $link): bool
    {
        return $user->hasPermission('seo.manage') || self::mayEditEntity($user, $entity, $link);
    }

    /** Whether the record has a card and this administrator may save it. */
    public static function mayEditEntity(HasPermissions $user, ?Model $entity, ?EntityLink $link): bool
    {
        return $entity instanceof Model
            && $link instanceof EntityLink
            && self::hasCard($entity)
            && $user->hasPermission($link->permission);
    }

    /**
     * What the tab shows for a path: the result, the origins, and where an edit goes.
     *
     * @return array<string, mixed>|null Null when this administrator has nothing to do here.
     */
    public function report(string $path, ?string $asked, HasPermissions $user): ?array
    {
        $url = UrlNormaliser::normalise($path);
        [$type, $entity, $rowLocale] = $this->find($url, $asked);
        $link = $type === null ? null : $this->bar->link($type);
        $mayEntity = self::mayEditEntity($user, $entity, $link);
        $mayRule = $user->hasPermission('seo.manage');

        if (! $mayEntity && ! $mayRule) {
            return null;
        }

        // The language of the page: its row's, or the one the page says it is in, or the site's.
        $locale = $rowLocale ?? $this->locales->content($asked);
        $target = $mayEntity ? 'entity' : 'rule';
        $matched = $this->rules->matching($url);
        $chain = $this->seo->chain($url, $entity, $locale);
        $final = $this->seo->for($url, $entity, $locale)->toArray();
        $above = $target === 'entity' ? $this->priority('entities', 50) : $this->priority('urls', 100);
        $own = $this->own($target, $entity, $matched, $locale);

        $fields = [];

        foreach (self::FIELDS as $field) {
            $won = $this->winner($chain, $field);
            $over = $this->winner($chain, $field, $above);

            $fields[$field] = [
                'value' => $final[$field] ?? null,
                'source' => $won === null ? null : self::sourceName($won['source']),
                'own' => $own[$field] ?? null,
                // Said before the save, not after it: an edit under a source that already speaks
                // for the field changes nothing a visitor sees (§7).
                'overridden' => $over === null ? null : self::sourceName($over['source']),
            ];
        }

        $titleSource = $fields['title']['source'];

        return [
            'url' => $url,
            'locale' => $locale,
            'target' => $target,
            'entity' => $mayEntity && $entity instanceof Model ? ['type' => $type, 'id' => $entity->getKey()] : null,
            'rule' => $matched === null ? null : [
                'id' => $matched->id,
                'pattern' => $matched->pattern,
                'match_type' => $matched->match_type,
                // Only a rule for this address alone is edited in place. A mask covers other
                // pages too; an edit here writes a rule of this page's own, which takes the
                // mask's place for this address and starts as a copy of it.
                'exact' => $matched->match_type === UrlMatcher::EXACT,
            ],
            'fields' => $fields,
            // What the page's `<title>` says now, for the script on the page to put in the tab
            // of the browser. Only when a rule or the record wrote it: a title a template made
            // up from its own fallback is not one this answer can know.
            'title' => in_array($titleSource, ['rule', 'entity'], true) ? $final['title'] : null,
            'can' => ['entity' => $mayEntity, 'rule' => $mayRule],
        ];
    }

    /**
     * The record behind an address, the way a request for it would be answered: the live row
     * that owns the whole path, its type, and the language of the row.
     *
     * @return array{0: string|null, 1: Model|null, 2: string|null}
     */
    public function find(string $url, ?string $locale = null): array
    {
        $resolution = $this->resolver->lookup($url, $locale);

        // A row that matched by prefix answers for a tail, and an alias only redirects: neither
        // is the page the tab is about. The page beside the bar answered 200, so this is rare.
        if ($resolution === null || $resolution->tail !== '' || $resolution->route->isAlias()) {
            return [null, null, null];
        }

        $row = $resolution->route;
        $type = $this->types->find($row->entity_type);

        if ($type === null || ! is_subclass_of($type->model, Model::class)) {
            return [null, null, $row->locale];
        }

        $entity = $type->model::query()->find($row->entity_id);

        return $entity instanceof Model ? [$type->type, $entity, $row->locale] : [null, null, $row->locale];
    }

    public static function hasCard(object $entity): bool
    {
        return in_array(HasSeo::class, class_uses_recursive($entity), true);
    }

    /**
     * What the place an edit goes to says now, in the page's language — the values the tab's
     * inputs start from. A mask stands in for the exact rule that does not exist yet, because
     * the new rule starts as its copy.
     *
     * @return array<string, string|null>
     */
    private function own(string $target, ?Model $entity, ?SeoUrl $matched, string $locale): array
    {
        $value = match (true) {
            $target === 'entity' && $entity instanceof Model && method_exists($entity, 'seoValue') => (array) $entity->seoValue(),
            $target === 'rule' && $matched instanceof SeoUrl => $matched->seoFieldValues(),
            default => [],
        };

        $own = [];

        foreach (self::FIELDS as $field) {
            $one = $value[$field] ?? null;
            $own[$field] = is_array($one) ? (is_string($one[$locale] ?? null) ? $one[$locale] : null) : (is_string($one) ? $one : null);
        }

        return $own;
    }

    /**
     * The first step of the chain that says something for this field — of all of them, or only
     * of those standing above a priority.
     *
     * @param  list<array{source: string, priority: int, data: array<string, mixed>}>  $chain
     * @return array{source: string, priority: int, data: array<string, mixed>}|null
     */
    private function winner(array $chain, string $field, ?int $above = null): ?array
    {
        foreach ($chain as $step) {
            if ($above !== null && $step['priority'] <= $above) {
                continue;
            }

            $value = $step['data'][$field] ?? null;

            if (is_string($value) && $value !== '') {
                return $step;
            }
        }

        return null;
    }

    /**
     * The chain names its sources by class; the tab names them by what they mean to an editor.
     * A project's own source is «another», which is still an answer: something above the
     * defaults said it, and the test-url screen in the panel says which.
     */
    private static function sourceName(string $class): string
    {
        return match ($class) {
            'UrlRuleSource' => 'rule',
            'EntitySource' => 'entity',
            'FallbackSource' => 'fallback',
            'DefaultsSource' => 'defaults',
            default => 'other',
        };
    }

    private function priority(string $source, int $default): int
    {
        return (int) $this->config->get("webx-seo.sources.{$source}", $default);
    }
}
