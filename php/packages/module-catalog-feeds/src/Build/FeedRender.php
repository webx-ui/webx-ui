<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Variables\ProductVariables;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\Templates\Compiled;
use WebxUi\Templates\Escapes;
use WebxUi\Templates\Template;
use WebxUi\Templates\TemplateError;

/**
 * Products and a template into text, the one code a build and a trial share (§8.1 of the feeds spec):
 * a sample that passes has to mean the file will. Where the text goes is the caller's.
 */
final readonly class FeedRender
{
    public function __construct(
        private FeedVariables $feedVariables,
        private ProductVariables $variables,
        private FeedContext $context,
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
        try {
            $names = $this->feedVariables->used((string) $feed->item);
        } catch (TemplateError $error) {
            throw FeedFailure::of('template', ['reason' => $error->words()]);
        }

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
     * A page through the third layer and the template: `$write` gets the text of each product that
     * goes in, in order.
     *
     * @param  Collection<int, Product>  $products  with their galleries
     * @param  list<string>  $names
     * @param  Closure(string, int): void  $write
     *
     * @throws FeedFailure
     */
    public function page(Feed $feed, Collection $products, Compiled $item, array $names, Closure $write): FeedPage
    {
        $rejected = [];
        $filtered = [];
        $warnings = [];
        $categories = [];
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

            $write($item->render($row), $id);

            foreach (FeedChecks::warnings($product, $row, $feed->locale) as $warning) {
                $warnings[$warning] = ($warnings[$warning] ?? 0) + 1;
            }

            if ($product->category_id !== null) {
                $categories[(int) $product->category_id] = true;
            }
        }

        return new FeedPage($rejected, $filtered, $warnings, array_keys($categories));
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

        $values = $this->context->values($feed, $build, $names);

        return [$this->part((string) $feed->head, $feed, $values), $this->part((string) $feed->foot, $feed, $values)];
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
