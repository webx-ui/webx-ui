<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Variables\ProductVariables;
use WebxUi\CatalogFeeds\Formats\FeedContext;
use WebxUi\CatalogFeeds\Formats\FeedFormats;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\Templates\Compiled;
use WebxUi\Templates\Escapes;
use WebxUi\Templates\Template;
use WebxUi\Templates\TemplateError;

/**
 * Products into values and a template into text, the one code a build and a trial share (§8.1 of the
 * feeds spec): a sample that passes has to mean the file will. Where the values go is the caller's.
 */
final readonly class FeedRender
{
    public function __construct(
        private FeedVariables $feedVariables,
        private ProductVariables $variables,
        private FeedFrame $frame,
        private FeedFormats $formats,
    ) {}

    /**
     * @throws FeedFailure
     */
    public function item(Feed $feed): Compiled
    {
        $template = (string) $feed->item;

        if (trim($template) === '') {
            throw FeedFailure::of('no-item');
        }

        return $this->compile($template, $feed);
    }

    /**
     * The variables a page has to compute for this feed.
     *
     * @return list<string>
     *
     * @throws FeedFailure
     */
    public function names(Feed $feed): array
    {
        $names = $this->asked($feed);

        foreach (FeedChecks::COMPANIONS as $name => $companion) {
            if (in_array($name, $names, true)) {
                $names[] = $companion;
            }
        }

        if ((bool) ($feed->filter['only_in_stock'] ?? false) && isset($this->variables->all()['is_purchasable'])) {
            $names[] = 'is_purchasable';
        }

        return array_values(array_unique($names));
    }

    /**
     * What the template uses, or what the class says it needs. A class reads its values by name, so
     * one the site does not have is a failure here, not a hole in the file.
     *
     * @return list<string>
     *
     * @throws FeedFailure
     */
    private function asked(Feed $feed): array
    {
        $format = $this->formats->of($feed);

        if ($format !== null) {
            $names = $format->variables();
            $unknown = array_diff($names, array_keys($this->variables->all()));

            return $unknown === [] ? $names : throw FeedFailure::of('format-variables', ['names' => implode(', ', $unknown)]);
        }

        try {
            return $this->feedVariables->used((string) $feed->item);
        } catch (TemplateError $error) {
            throw FeedFailure::of('template', ['reason' => $error->words()]);
        }
    }

    /**
     * The next products by the cursor.
     *
     * @param  Builder<Product>  $selected
     * @return Collection<int, Product>
     */
    public function products(Builder $selected, int $after, int $limit): Collection
    {
        return (clone $selected)
            ->with('images')
            ->where('catalog_products.id', '>', $after)
            ->orderBy('catalog_products.id')
            ->limit($limit)
            ->get();
    }

    /**
     * A page through the third layer: `$write` gets the values of each product that goes in, in
     * order.
     *
     * @param  Collection<int, Product>  $products  with their galleries
     * @param  list<string>  $names
     * @param  Closure(array<string, mixed>, int): void  $write
     *
     * @throws FeedFailure
     */
    public function page(Feed $feed, Collection $products, array $names, Closure $write): FeedPage
    {
        $rejected = [];
        $filtered = [];
        $warnings = [];
        $categories = [];
        $labels = [];
        $kept = new Collection;

        foreach ($products as $product) {
            $reason = FeedChecks::reason($product, $feed->locale);

            if ($reason === null) {
                $kept->push($product);
            } else {
                $rejected[(int) $product->id] = $reason;
            }
        }

        $rows = $kept->isEmpty() ? [] : $this->variables->values($kept, $feed->locale, $names, $this->feedVariables->overrides($feed));
        $onlyInStock = in_array('is_purchasable', $names, true) && (bool) ($feed->filter['only_in_stock'] ?? false);

        foreach ($kept as $product) {
            $id = (int) $product->id;
            $row = $rows[$id];

            if ($onlyInStock && $row['is_purchasable'] === '') {
                $filtered[] = $id;

                continue;
            }

            $write($row, $id);

            foreach (FeedChecks::warnings($product, $row, $feed->locale) as $warning) {
                $warnings[$warning] = ($warnings[$warning] ?? 0) + 1;
            }

            if ($product->category_id !== null) {
                $categories[(int) $product->category_id] = true;
            }

            for ($label = 0; $label < FeedLabels::COUNT; $label++) {
                $value = $row['custom_label_'.$label] ?? '';

                if (is_string($value) && $value !== '') {
                    $labels[$label][$value] = true;
                }
            }
        }

        return new FeedPage($rejected, $filtered, $warnings, array_keys($categories), array_map(
            static fn (array $values): array => array_map(strval(...), array_keys($values)),
            $labels,
        ));
    }

    /**
     * Every variable of these products under the feed's settings: what a preview in the browser
     * fills whatever is typed next with (§8.3).
     *
     * @param  Collection<int, Product>  $products  with their galleries
     * @return array<int, array<string, mixed>>
     *
     * @throws FeedFailure
     */
    public function values(Feed $feed, Collection $products): array
    {
        return $this->variables->values($products, $feed->locale, null, $this->feedVariables->overrides($feed));
    }

    /**
     * The head and the foot, once the pages have said what they speak about.
     *
     * @return array{string, string}
     *
     * @throws FeedFailure
     */
    public function frame(Feed $feed, FeedBuild $build): array
    {
        try {
            $names = array_column([...Template::usages((string) $feed->head), ...Template::usages((string) $feed->foot)], 'name');
        } catch (TemplateError $error) {
            throw FeedFailure::of('template', ['reason' => $error->words()]);
        }

        $values = $this->frame->values($feed, $build, $names);

        return [$this->part((string) $feed->head, $feed, $values), $this->part((string) $feed->foot, $feed, $values)];
    }

    /** The same words for a class: it does not say which it will use, so all of them. */
    public function context(Feed $feed, FeedBuild $build): FeedContext
    {
        return new FeedContext($feed, $this->frame->values($feed, $build, ['categories']));
    }

    /**
     * @param  array<string, string|list<array<string, string>>>  $values
     */
    private function part(string $template, Feed $feed, array $values): string
    {
        return $template === '' ? '' : $this->compile($template, $feed)->render($values);
    }

    private function compile(string $template, Feed $feed): Compiled
    {
        try {
            return Template::compile($template, Escapes::for($feed->format, ['delimiter' => FeedFileCheck::delimiter($feed)]));
        } catch (TemplateError $error) {
            throw FeedFailure::of('template', ['reason' => $error->words()]);
        }
    }
}
