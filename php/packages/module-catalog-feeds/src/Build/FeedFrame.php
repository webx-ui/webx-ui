<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use WebxUi\Catalog\Exchange\Columns\CategoryPaths;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\Routing\Models\Route;
use WebxUi\Templates\Variable;
use WebxUi\Templates\Variables;

/**
 * What a head and a foot may say (§4.6 of the feeds spec). They have no product: they speak about
 * the shop, the feed and what the pages found, which is why they are written last.
 */
final readonly class FeedFrame
{
    public function __construct(private Config $config) {}

    /**
     * What a head and a foot may name.
     *
     * @return Variables<Variable>
     */
    public function variables(): Variables
    {
        $texts = [
            'shop.name', 'shop.url', 'shop.description', 'shop.email', 'shop.phone', 'feed.name',
            'feed.locale', 'feed.currency', 'generated_at', 'generated_at_rfc822', 'products_count',
        ];

        $word = static fn (string $name): string => 'webx-catalog-feeds::variables.'.str_replace('.', '-', $name);

        return new Variables([
            ...array_map(static fn (string $name): Variable => new Variable($name, $word($name)), $texts),
            new Variable('currencies', $word('currencies'), [], ['code' => $word('currency-code'), 'rate' => $word('currency-rate')]),
            new Variable('categories', $word('categories'), [], [
                'id' => $word('category-id'),
                'parent_id' => $word('category-parent'),
                'name' => $word('category-name'),
                'path' => $word('category-path'),
                'url' => $word('category-url'),
            ]),
        ]);
    }

    /**
     * @param  list<string>  $used  the names the head and the foot use
     * @return array<string, string|list<array<string, string>>>
     */
    public function values(Feed $feed, FeedBuild $build, array $used): array
    {
        $now = Carbon::now();

        return [
            ...$this->shop($feed),
            'feed.name' => $feed->name,
            'feed.locale' => $feed->locale,
            'feed.currency' => $feed->currency,
            'generated_at' => $now->toIso8601String(),
            'generated_at_rfc822' => $now->toRfc822String(),
            'currencies' => [['code' => $feed->currency, 'rate' => '1']],
            'categories' => in_array('categories', $used, true) ? $this->categories($feed, $build) : [],
            'products_count' => (string) $build->written,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function shop(Feed $feed): array
    {
        // `module-settings` is not a dependency of a feed: the shop has a name without it.
        $settings = function_exists('settings');
        $name = $settings ? settings('general.project-name') : null;
        $contacts = $settings && function_exists('contacts') ? contacts() : null;

        return [
            'shop.name' => is_string($name) && $name !== '' ? $name : (string) $this->config->get('app.name'),
            'shop.url' => rtrim((string) $this->config->get('app.url'), '/'),
            'shop.description' => (string) ($feed->options['description'] ?? ''),
            'shop.email' => (string) ($contacts?->emails($feed->locale)[0]->address ?? ''),
            'shop.phone' => (string) ($contacts?->primaryPhone($feed->locale)->number ?? ''),
        ];
    }

    /**
     * The categories of what was written, with their ancestors: a `parent_id` must lead somewhere.
     *
     * @return list<array<string, string>>
     */
    private function categories(Feed $feed, FeedBuild $build): array
    {
        $locale = $feed->locale;
        $tree = Category::query()->with('routes')->orderBy('lft')->get()->keyBy('id');
        $chosen = [];

        foreach ($build->state['categories'] ?? [] as $id) {
            while ($id !== null && isset($tree[$id]) && ! isset($chosen[$id])) {
                $chosen[$id] = true;
                $id = $tree[$id]->parent_id === null ? null : (int) $tree[$id]->parent_id;
            }
        }

        $paths = new CategoryPaths($locale);
        $rows = [];

        foreach ($tree as $id => $category) {
            if (! isset($chosen[$id])) {
                continue;
            }

            /** @var Category $category */
            $name = $category->getTranslation('name', $locale, false);
            $route = $category->routes->first(static fn (Route $route): bool => $route->kind === Route::CANONICAL && $route->locale === $locale);

            $rows[] = [
                'id' => (string) $id,
                'parent_id' => (string) $category->parent_id,
                'name' => is_string($name) ? $name : '',
                'path' => implode(' > ', $paths->names((int) $id)),
                'url' => $route instanceof Route ? $category->urlOf($route->path, $locale) : '',
            ];
        }

        return $rows;
    }
}
