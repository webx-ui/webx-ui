<?php

declare(strict_types=1);

namespace WebxUi\Seo\Mcp;

use WebxUi\Mcp\Tool;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Panel\UrlRuleSource;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\UrlNormaliser;

/**
 * What an agent can do with SEO.
 *
 * The two that earn their keep are `test_url` — the answer to "why does this page say that" —
 * and `import_redirects`, which turns the list of old and new addresses that comes out of every
 * site migration into rows, in one call rather than three hundred clicks.
 */
final class SeoTools
{
    /**
     * @return list<Tool>
     */
    public static function all(): array
    {
        return [
            Tool::read(
                'urls_list',
                'The rules written for addresses, in the order the site tries them.',
                static fn (array $arguments): array => self::urls($arguments),
                [
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Part of a pattern'],
                        'match_type' => ['type' => 'string', 'enum' => UrlMatcher::types()],
                        'page' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
                scope: 'seo:read',
            ),

            Tool::read(
                'urls_get',
                'One rule, every field, every language.',
                static function (array $arguments): array {
                    $rule = SeoUrl::query()->find((int) ($arguments['id'] ?? 0));

                    return $rule instanceof SeoUrl
                        ? ['ok' => true, 'rule' => self::describe($rule)]
                        : ['ok' => false, 'reason' => 'No rule with that id.'];
                },
                [
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
                scope: 'seo:read',
            ),

            Tool::mutating(
                'urls_set',
                'Write a rule for an address. With an id it replaces that rule; without one it adds a rule. Text fields take an object keyed by language code.',
                static fn (array $arguments): array => self::setUrl($arguments),
                [
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'The rule to change; leave out to add one'],
                        'match_type' => ['type' => 'string', 'enum' => UrlMatcher::types()],
                        'pattern' => ['type' => 'string', 'description' => 'An address, a mask (/catalog/*) or a regular expression'],
                        'priority' => ['type' => 'integer'],
                        'title' => ['type' => 'object'],
                        'h1' => ['type' => 'object'],
                        'description' => ['type' => 'object'],
                        'keywords' => ['type' => 'object'],
                        'canonical' => ['type' => 'string'],
                        'robots' => ['type' => 'string', 'description' => 'Meta directives, comma separated — not robots.txt'],
                        'is_active' => ['type' => 'boolean'],
                    ],
                    'required' => ['match_type', 'pattern'],
                ],
                scope: 'seo:write',
            ),

            Tool::read(
                'test_url',
                'What an address ends up saying about itself, and where every part of it came from: the redirect that catches it, the rule that matched, and each source in turn.',
                static fn (array $arguments): array => self::test($arguments),
                [
                    'properties' => [
                        'url' => ['type' => 'string', 'description' => 'A path, with its query string if it has one'],
                        'locale' => ['type' => 'string', 'description' => 'The language to read it in'],
                    ],
                    'required' => ['url'],
                ],
                scope: 'seo:read',
            ),

            Tool::read(
                'redirects_list',
                'Addresses that have moved, busiest first.',
                static fn (array $arguments): array => self::redirects($arguments),
                [
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Part of a pattern or a target'],
                        'page' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
                scope: 'seo:read',
            ),

            Tool::mutating(
                'redirects_set',
                'Write one redirect. With an id it replaces that one; without one it adds it.',
                static fn (array $arguments): array => self::setRedirect($arguments),
                [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'match_type' => ['type' => 'string', 'enum' => UrlMatcher::types()],
                        'pattern' => ['type' => 'string'],
                        'target' => ['type' => 'string'],
                        'status' => ['type' => 'integer', 'enum' => [301, 302]],
                        'is_active' => ['type' => 'boolean'],
                    ],
                    'required' => ['pattern', 'target'],
                ],
                scope: 'seo:write',
            ),

            Tool::mutating(
                'import_redirects',
                'Add a list of redirects at once — what comes out of a migration as "old address, new address". An address that already has a redirect is left alone and reported.',
                static fn (array $arguments): array => self::import($arguments),
                [
                    'properties' => [
                        'redirects' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'from' => ['type' => 'string'],
                                    'to' => ['type' => 'string'],
                                    'status' => ['type' => 'integer', 'enum' => [301, 302]],
                                ],
                                'required' => ['from', 'to'],
                            ],
                        ],
                    ],
                    'required' => ['redirects'],
                ],
                scope: 'seo:write',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function urls(array $arguments): array
    {
        $query = SeoUrl::query()
            ->orderByRaw("case match_type when 'exact' then 0 when 'mask' then 1 else 2 end")
            ->orderByDesc('priority')
            ->orderBy('id');

        if (is_string($arguments['q'] ?? null) && $arguments['q'] !== '') {
            $query->where('pattern', 'like', '%'.addcslashes($arguments['q'], '%_\\').'%');
        }

        if (is_string($arguments['match_type'] ?? null)) {
            $query->where('match_type', $arguments['match_type']);
        }

        $page = $query->paginate(50, ['*'], 'page', max(1, (int) ($arguments['page'] ?? 1)));

        return [
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'rules' => array_map(self::summarise(...), $page->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function redirects(array $arguments): array
    {
        $query = SeoRedirect::query()->orderByDesc('hits')->orderBy('id');

        if (is_string($arguments['q'] ?? null) && $arguments['q'] !== '') {
            $term = '%'.addcslashes($arguments['q'], '%_\\').'%';
            $query->where(fn ($where) => $where->where('pattern', 'like', $term)->orWhere('target', 'like', $term));
        }

        $page = $query->paginate(50, ['*'], 'page', max(1, (int) ($arguments['page'] ?? 1)));

        return [
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'redirects' => array_map(static fn (SeoRedirect $redirect): array => [
                'id' => $redirect->id,
                'match_type' => $redirect->match_type,
                'pattern' => $redirect->pattern,
                'target' => $redirect->target,
                'status' => $redirect->status,
                'is_active' => $redirect->is_active,
                'hits' => $redirect->hits,
                'is_loop' => $redirect->isLoop(),
            ], $page->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function test(array $arguments): array
    {
        $url = UrlNormaliser::normalise((string) ($arguments['url'] ?? '/'));
        $locale = is_string($arguments['locale'] ?? null) ? $arguments['locale'] : null;

        $seo = app(Seo::class);
        $matched = app(UrlRuleSource::class)->matching($url);

        return [
            'url' => $url,
            'matched' => $matched === null ? null : self::summarise($matched),
            'chain' => $seo->chain($url, null, $locale),
            'seo' => $seo->for($url, null, $locale)->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function setUrl(array $arguments): array
    {
        $rule = isset($arguments['id']) ? SeoUrl::query()->find((int) $arguments['id']) : new SeoUrl;

        if (! $rule instanceof SeoUrl) {
            return ['ok' => false, 'reason' => 'No rule with that id.'];
        }

        $matchType = (string) ($arguments['match_type'] ?? UrlMatcher::EXACT);
        $pattern = (string) ($arguments['pattern'] ?? '');

        if (! in_array($matchType, UrlMatcher::types(), true) || $pattern === '') {
            return ['ok' => false, 'reason' => 'A rule needs a kind and a pattern.'];
        }

        if ($matchType === UrlMatcher::REGEX && ! UrlMatcher::isValidRegex($pattern)) {
            return ['ok' => false, 'reason' => 'That regular expression will not compile.'];
        }

        $values = [
            'match_type' => $matchType,
            'pattern' => $matchType === UrlMatcher::REGEX ? $pattern : UrlNormaliser::normalise($pattern),
        ];

        foreach (['title', 'h1', 'description', 'keywords'] as $field) {
            if (is_array($arguments[$field] ?? null)) {
                $values[$field] = $arguments[$field];
            }
        }

        foreach (['canonical', 'robots'] as $field) {
            if (is_string($arguments[$field] ?? null)) {
                $values[$field] = $arguments[$field];
            }
        }

        if (isset($arguments['priority'])) {
            $values['priority'] = (int) $arguments['priority'];
        }

        if (isset($arguments['is_active'])) {
            $values['is_active'] = (bool) $arguments['is_active'];
        }

        if ($arguments['dry_run'] ?? false) {
            return ['ok' => true, 'would_change' => true, 'applied' => false, 'values' => $values];
        }

        $rule->fill($values)->save();

        return ['ok' => true, 'applied' => true, 'id' => $rule->id];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function setRedirect(array $arguments): array
    {
        $redirect = isset($arguments['id']) ? SeoRedirect::query()->find((int) $arguments['id']) : new SeoRedirect;

        if (! $redirect instanceof SeoRedirect) {
            return ['ok' => false, 'reason' => 'No redirect with that id.'];
        }

        $matchType = (string) ($arguments['match_type'] ?? UrlMatcher::EXACT);
        $pattern = (string) ($arguments['pattern'] ?? '');
        $target = (string) ($arguments['target'] ?? '');

        if (! in_array($matchType, UrlMatcher::types(), true) || $pattern === '' || $target === '') {
            return ['ok' => false, 'reason' => 'A redirect needs a pattern and a target.'];
        }

        $values = [
            'match_type' => $matchType,
            'pattern' => $matchType === UrlMatcher::REGEX ? $pattern : UrlNormaliser::normalise($pattern),
            'target' => str_contains($target, '://') ? $target : UrlNormaliser::normalise($target),
            'status' => in_array((int) ($arguments['status'] ?? 301), [301, 302], true) ? (int) ($arguments['status'] ?? 301) : 301,
            'is_active' => (bool) ($arguments['is_active'] ?? true),
        ];

        if ($arguments['dry_run'] ?? false) {
            return ['ok' => true, 'would_change' => true, 'applied' => false, 'values' => $values];
        }

        $redirect->fill($values)->save();

        return ['ok' => true, 'applied' => true, 'id' => $redirect->id];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function import(array $arguments): array
    {
        $rows = is_array($arguments['redirects'] ?? null) ? $arguments['redirects'] : [];
        $existing = SeoRedirect::query()->pluck('pattern')->all();

        $added = [];
        $skipped = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $from = UrlNormaliser::normalise((string) ($row['from'] ?? ''));
            $to = (string) ($row['to'] ?? '');
            $to = str_contains($to, '://') ? trim($to) : UrlNormaliser::normalise($to);

            if ($from === '/' || $to === '' || $from === $to) {
                $skipped[] = ['from' => $from, 'why' => 'an address that goes nowhere, or to itself'];

                continue;
            }

            if (in_array($from, $existing, true)) {
                $skipped[] = ['from' => $from, 'why' => 'already redirected'];

                continue;
            }

            $existing[] = $from;
            $added[] = ['from' => $from, 'to' => $to];

            if ($arguments['dry_run'] ?? false) {
                continue;
            }

            SeoRedirect::query()->create([
                'match_type' => UrlMatcher::EXACT,
                'pattern' => $from,
                'target' => $to,
                'status' => in_array((int) ($row['status'] ?? 301), [301, 302], true) ? (int) ($row['status'] ?? 301) : 301,
                'is_active' => true,
            ]);
        }

        return [
            'ok' => true,
            'applied' => ! ($arguments['dry_run'] ?? false),
            'added' => count($added),
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function summarise(SeoUrl $rule): array
    {
        return [
            'id' => $rule->id,
            'match_type' => $rule->match_type,
            'pattern' => $rule->pattern,
            'priority' => $rule->priority,
            'is_active' => $rule->is_active,
            'title' => $rule->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function describe(SeoUrl $rule): array
    {
        // Translations first: `+` keeps the left-hand key, and a rule asked for by id should
        // come back with every language of its title rather than with one of them.
        return $rule->translationsToArray() + self::summarise($rule) + [
            'canonical' => $rule->canonical,
            'robots' => $rule->robots,
            'og_image' => $rule->og_image,
            'json_ld' => $rule->json_ld,
        ];
    }
}
