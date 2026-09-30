<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Columns;

use Illuminate\Support\Collection;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Catalog\Exchange\FetchExchangeImages;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Exchange\WritesProduct;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;

/**
 * The gallery as addresses through `;` (decision 16 of the exchange spec). The export writes the
 * public address of every picture; the import downloads what the gallery does not have yet — on
 * the queue, after the row is committed, never inside the chunk.
 *
 * "Does not have yet" is either of two things: the address is one of the gallery's own pictures
 * (a file exported and sent back), or a picture was once downloaded from it — its hash is kept
 * beside the picture. So a supplier's price list sent every night downloads each picture once.
 *
 * `images: append` (the default) adds what is missing; `replace` also takes off what the cell
 * does not name.
 */
final class ImagesColumn implements WritesProduct
{
    public const APPEND = 'append';

    public const REPLACE = 'replace';

    public function key(): string
    {
        return 'images';
    }

    public function label(): string
    {
        return (string) __('webx-catalog::product.images');
    }

    public function field(): string
    {
        return 'images';
    }

    public function localized(): bool
    {
        return false;
    }

    public function export(Collection $products, ?string $locale): array
    {
        $ids = $products->map(static fn (Product $product): int => (int) $product->id)->all();
        $urls = [];

        foreach (ProductImage::query()->whereIn('product_id', $ids)->orderBy('position')->orderBy('id')->get() as $image) {
            $urls[$image->product_id][] = self::absolute($image->url());
        }

        $cells = [];

        foreach ($ids as $id) {
            $cells[$id] = implode(';', $urls[$id] ?? []);
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        $urls = [];

        foreach (preg_split('/[;\r\n]+/', $cell) ?: [] as $one) {
            $one = trim($one);

            if ($one === '') {
                continue;
            }

            if (filter_var($one, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($one, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                throw RowError::because('not-a-url', ['url' => $one]);
            }

            $urls[] = $one;
        }

        return array_values(array_unique($urls));
    }

    public function write(Product $product, mixed $value, ImportContext $context): void
    {
        /** @var list<string> $wanted */
        $wanted = is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
        $replace = ($context->options['images'] ?? self::APPEND) === self::REPLACE;

        if ($wanted === [] && ! $replace) {
            return;
        }

        $byUrl = [];
        $byHash = [];

        foreach ($product->images()->get() as $image) {
            $byUrl[self::absolute($image->url())] = (int) $image->id;

            if ($image->source_hash !== null) {
                $byHash[$image->source_hash] = (int) $image->id;
            }
        }

        $kept = [];
        $missing = [];

        foreach ($wanted as $url) {
            $id = $byUrl[$url] ?? $byHash[sha1($url)] ?? null;

            if ($id === null) {
                $missing[] = $url;
            } else {
                $kept[$id] = true;
            }
        }

        $remove = $replace ? array_values(array_diff(array_values($byUrl), array_keys($kept))) : [];

        if ($context->dryRun || ($missing === [] && $remove === [])) {
            return;
        }

        $history = app(HistoryContext::class);

        // After the commit, and only if it is one: a row rolled back to its savepoint takes its
        // job with it, and the worker must find the product the row created.
        dispatch(new FetchExchangeImages(
            (int) $product->id,
            $missing,
            $remove,
            $history->runId(),
            $history->adminId(),
            $history->adminName(),
            $context->runId,
            $context->row,
        ))->afterCommit();
    }

    /** A disk that answers `/storage/…` is given the site's host: a file leaves the site. */
    private static function absolute(string $url): string
    {
        return preg_match('#^https?://#i', $url) === 1 ? $url : url($url);
    }
}
