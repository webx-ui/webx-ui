<?php

declare(strict_types=1);

namespace WebxUi\Press\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Support\Kinds;
use WebxUi\Routing\Models\Route;

/**
 * An outlet and an article as a template reads them — plain data, not the models (§4.8).
 *
 * An outlet:
 *
 *     id, title, summary      the name from any language that has it; the summary in this one or ''
 *     url                     its page — null without pages, or without an address in this language
 *     link                    where its logo leads: the page, else the outlet's own site
 *     logo                    what a `wx-media` field hands over: url, width, height, alt… or null
 *     website, featured
 *     count, kinds            how many of its articles are seen, and their kinds in the config's order
 *     fields                  the project's own fields, by name
 *
 * An article:
 *
 *     id, title, excerpt      in this language; '' when the excerpt is not written in it
 *     outlet                  { id, title, url, logo } — the logo's address, or null
 *     kind, kind_label        the key and its word — both null for no kind
 *     date, when              `Y-m-d` and the words of §4.5 — null and '' without a date
 *     target, url, pdf        where the title leads, the address, the PDF — each null when there is none
 *     fields                  the project's own fields of the article row, by name
 *
 * Built from what was loaded with the list, so a list of any length is the same few queries.
 */
final class Cards
{
    public function __construct(
        private readonly MediaFiles $files,
        private readonly ScreenRecord $record,
        private readonly ScreenValues $values,
        private readonly Config $config,
    ) {}

    /**
     * @param  list<Outlet>  $outlets  Loaded with their articles, and with their canonical rows.
     * @return list<array<string, mixed>>
     */
    public function outlets(array $outlets, string $locale): array
    {
        $this->files->load(array_values(array_filter(array_map(
            static fn (Outlet $outlet): ?string => $outlet->logoPath(),
            $outlets,
        ))));

        return array_map(fn (Outlet $outlet): array => $this->outlet($outlet, $locale), $outlets);
    }

    /**
     * @param  list<Article>  $articles  Loaded with their outlets.
     * @return list<array<string, mixed>>
     */
    public function articles(array $articles, string $locale): array
    {
        $paths = [];

        foreach ($articles as $article) {
            $paths[] = $article->filePath();
            $paths[] = $article->outlet?->logoPath();
        }

        $this->files->load(array_values(array_filter($paths)));

        return array_map(fn (Article $article): array => $this->article($article, $locale), $articles);
    }

    /**
     * @return array<string, mixed>
     */
    public function outlet(Outlet $outlet, string $locale): array
    {
        $seen = $outlet->articles->filter(static fn (Article $article): bool => $article->visibleIn($locale));
        $kinds = [];

        foreach ($seen as $article) {
            $kind = $article->kindKey();

            if ($kind !== null) {
                $kinds[$kind] = true;
            }
        }

        $fields = [];

        foreach (array_keys((array) ($outlet->extraRaw() ?? [])) as $name) {
            $fields[(string) $name] = $outlet->extra((string) $name, $locale);
        }

        $url = $this->url($outlet, $locale);

        return [
            'id' => (int) $outlet->getKey(),
            'url' => $url,
            'link' => $url ?? $outlet->website(),
            'title' => $outlet->displayTitle($locale),
            'summary' => $outlet->text('summary', $locale),
            'logo' => $outlet->logoIn($locale),
            'website' => $outlet->website(),
            'featured' => $outlet->featured,
            'count' => $seen->count(),
            'kinds' => array_values(array_filter(Kinds::all(), static fn (string $key): bool => isset($kinds[$key]))),
            'fields' => $fields,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function article(Article $article, string $locale): array
    {
        $outlet = $article->outlet;
        $kind = $article->kindKey();
        $logo = $outlet?->logoIn($locale);

        return [
            'id' => (int) $article->getKey(),
            'outlet' => $outlet === null ? null : [
                'id' => (int) $outlet->getKey(),
                'title' => $outlet->displayTitle($locale),
                'url' => $this->url($outlet, $locale),
                'logo' => $logo['url'] ?? null,
            ],
            'title' => $article->text('title', $locale),
            'excerpt' => $article->text('excerpt', $locale),
            'kind' => $kind,
            'kind_label' => $kind === null ? null : Kinds::label($kind, $locale),
            'date' => $article->published_on?->toDateString(),
            'when' => When::of($article, $locale),
            'target' => $article->target($locale),
            'url' => $article->link(),
            'pdf' => $article->pdf($locale),
            'fields' => $this->articleFields($article, $locale),
        ];
    }

    /**
     * The outlet's page in this language — from the rows loaded with it when they were, so a list
     * does not go back to the registry once per card.
     */
    public function url(Outlet $outlet, string $locale): ?string
    {
        if (! (bool) $this->config->get('webx-press.pages', true) || ! $outlet->hasPages()) {
            return null;
        }

        $canonical = $outlet->relationLoaded('routes')
            ? $outlet->routes->first(static fn (Route $row): bool => $row->locale === $locale && $row->kind === Route::CANONICAL)
            : $outlet->routeCanonical($locale);

        return $canonical instanceof Route ? $outlet->urlOf($canonical->path, $locale) : null;
    }

    /**
     * A project's fields on an article are children of the articles repeater, not of the screen,
     * so they are read through their own node there.
     *
     * @return array<string, mixed>
     */
    private function articleFields(Article $article, string $locale): array
    {
        $stored = (array) ($article->extraRaw() ?? []);

        if ($stored === []) {
            return [];
        }

        $repeater = $this->record->field(Outlet::SCREEN, 'articles');
        $nodes = [];

        foreach ($repeater === null ? [] : Tree::fields(Tree::children($repeater)) as $node) {
            $nodes[(string) $node['name']] = $node;
        }

        $fields = [];

        foreach ($stored as $name => $value) {
            $node = $nodes[(string) $name] ?? null;
            $fields[(string) $name] = $node === null ? $value : $this->values->resolve($node, $value, $locale);
        }

        return $fields;
    }
}
