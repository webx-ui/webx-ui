<?php

declare(strict_types=1);

namespace WebxUi\Press\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Rendering\When;
use WebxUi\Press\Support\Kinds;

/**
 * What an agent reads before it writes an outlet or an article (§4.11): all of them in one message.
 *
 * Every outlet not in the bin, in its order, with its articles in theirs. Unpublished outlets and
 * hidden articles are in it and say so — the point of reading this first is not to type an
 * interview in a second time beside the copy that is not out yet.
 *
 * One name per row, in the language the agent works in; `visible_in` says where a reader sees the
 * thing at all (decision 7), `written_in` where an article's title is, and `press_get` has every
 * language of the outlet it picks.
 */
final class PressResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'press://catalog',
                'Press catalog',
                'Every outlet in its order, with its articles in theirs — the name, whether it is published and in '
                .'the strip of logos, the languages a reader sees it in; for each article its kind, when it ran, '
                .'where its title leads, the languages its title is written in and the ones it is seen in. Read it '
                .'before adding an outlet or an article, so that you reuse rather than duplicate.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locales = $this->container->make(Locales::class);
        $locale = $locales->current();
        $codes = $locales->codes();

        return [
            'locales' => $codes,
            'default_locale' => $locales->defaultCode(),
            'kinds' => Kinds::all(),
            'outlets' => Outlet::query()->with('articles')->ordered()->get()->map(static function (Outlet $outlet) use ($locale, $codes): array {
                $has = $outlet->localesWithArticles();

                return [
                    'id' => (int) $outlet->getKey(),
                    'title' => $outlet->displayTitle($locale),
                    'website_url' => $outlet->website_url,
                    'published' => $outlet->published,
                    'featured' => $outlet->featured,
                    'visible_in' => $outlet->published ? $has : [],
                    'articles' => $outlet->articles->map(static function (Article $article) use ($outlet, $locale, $codes): array {
                        $written = array_values(array_filter($codes, static fn (string $code): bool => $article->text('title', $code) !== ''));
                        $title = $article->text('title', $locale);

                        return [
                            'id' => (int) $article->getKey(),
                            'title' => $title !== '' ? $title : (string) ($article->getTranslations('title')[$written[0] ?? ''] ?? ''),
                            'kind' => $article->kind,
                            'when' => When::of($article, $locale),
                            'target' => $article->target(),
                            'is_hidden' => $article->is_hidden,
                            'written_in' => $written,
                            'visible_in' => $outlet->published ? array_values(array_filter($codes, $article->visibleIn(...))) : [],
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
