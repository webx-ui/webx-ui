<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Contracts\Container\Container;
use WebxUi\Catalog\Variables\ProductVariables;
use WebxUi\Catalog\Variables\StockStatuses;
use WebxUi\CatalogFeeds\Build\FeedFrame;
use WebxUi\CatalogFeeds\Build\FeedLabels;
use WebxUi\CatalogFeeds\Formats\FeedFormat;
use WebxUi\CatalogFeeds\Formats\FeedFormats;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Presets\FeedPreset;
use WebxUi\CatalogFeeds\Presets\FeedPresets;
use WebxUi\Templates\Variable;

/**
 * What a feed is made from, the same to a form and to an agent.
 */
final readonly class FeedMeta
{
    public function __construct(
        private FeedPresets $presets,
        private ProductVariables $products,
        private FeedFrame $frame,
        private FeedFormats $formats,
        private Container $container,
    ) {}

    /**
     * What a new feed starts from: a preset with all it copies, so a form can show it unsaved.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [
            'presets' => array_map(static fn (FeedPreset $preset): array => [
                'key' => $preset->key(),
                'name' => $preset->name(),
                'description' => $preset->description(),
                'values' => $preset->values(),
            ], $this->presets->all()),
            'formats' => array_keys(Feed::FORMATS),
            // What a form shows instead of the template (§10).
            'drivers' => array_map(static fn (FeedFormat $format): array => [
                'key' => $format->key(),
                'label' => $format->label(),
                'class' => $format::class,
                'extension' => $format->extension(),
                'content_type' => $format->contentType(),
            ], $this->formats->all()),
            'label_sources' => FeedLabels::TYPES,
            'currency' => Feed::siteCurrency(),
            // Only the stock satellite knows what can be bought (§5.1): without it there is no option.
            'stock' => isset($this->products->all()['is_purchasable']),
            'stock_statuses' => $this->container->bound(StockStatuses::class) ? $this->container->make(StockStatuses::class)->all(app()->getLocale()) : [],
        ];
    }

    /**
     * What the editor suggests, by part: a head and a foot have no product (§4.6, §11).
     *
     * @return array{item: array<array-key, array<string, mixed>>, frame: array<array-key, array<string, mixed>>}
     */
    public function variables(): array
    {
        $words = static fn (Variable $variable): array => $variable->toArray();

        return [
            'item' => array_map($words, $this->products->variables()->variables),
            'frame' => array_map($words, $this->frame->variables()->variables),
        ];
    }
}
