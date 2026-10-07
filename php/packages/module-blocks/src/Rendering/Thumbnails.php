<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Throwable;
use WebxUi\Blocks\BlockType;
use WebxUi\Localization\Locales;

/**
 * The picture of a block type on its sample, for the list of types and the picker — drawn once
 * and kept.
 *
 * Drawing is not free: a type that reads records (recipes, events, reviews) runs its queries on
 * every draw, and the list draws every type. Before this the list took half a second on a site
 * with forty types and did all of it again on the next request.
 *
 * Kept by the version drawn and the language it was drawn in, under a generation that any change
 * to any type moves on ({@see self::forget()}, from the model's events): a new version, a
 * publication, a restore, a setting, a rename, a delete, an import, a customised component. Any
 * type rather than this one, because a type that calls another prints it too — and a list of a
 * few dozen thumbnails redrawn after a save is cheap; one drawn stale is not. The site's
 * stylesheet is not part of what is kept: the frame loads it beside the HTML.
 *
 * A type whose sample reads records is a picture of those records, which change without any type
 * changing: hence a lifetime too (`webx-blocks.thumbnails.ttl`, an hour by default) — the most a
 * thumbnail of a recipe card can lag behind the recipes.
 */
final class Thumbnails
{
    private const GENERATION = 'webx.blocks.thumbnails.generation';

    public function __construct(
        private readonly Renderer $renderer,
        private readonly Cache $cache,
        private readonly Config $config,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array{html: string, styles: string, empty: bool}
     */
    public function of(BlockType $type): array
    {
        if (! $this->enabled()) {
            return $this->draw($type);
        }

        try {
            $key = sprintf('webx.blocks.thumbnails.%s.%d.%s', $this->generation(), $type->versionId, $this->locales->content());

            /** @var array{html: string, styles: string, empty: bool} */
            return $this->cache->remember($key, $this->ttl(), fn (): array => $this->draw($type));
        } catch (Throwable) {
            return $this->draw($type);
        }
    }

    /** Every thumbnail drawn so far is stale: the next request draws them again. */
    public function forget(): void
    {
        try {
            $this->cache->forever(self::GENERATION, Str::random(8));
        } catch (Throwable) {
            // No cache to tell — a console command before the store is configured.
        }
    }

    /**
     * Whether a drawn block printed anything at all: the markers and comments taken off, then
     * whitespace. A data block with nothing to show prints nothing, and a blank card in the list
     * says nothing about why.
     */
    public static function isEmpty(string $html): bool
    {
        $text = (string) preg_replace('/<!--.*?-->/s', '', $html);

        return trim($text) === '';
    }

    /**
     * @return array{html: string, styles: string, empty: bool}
     */
    private function draw(BlockType $type): array
    {
        $html = $this->renderer->draw($type, $type->sample);

        return ['html' => $html, 'styles' => $type->styles, 'empty' => self::isEmpty($html)];
    }

    private function generation(): string
    {
        $generation = $this->cache->get(self::GENERATION);

        if (! is_string($generation) || $generation === '') {
            $generation = Str::random(8);
            $this->cache->forever(self::GENERATION, $generation);
        }

        return $generation;
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('webx-blocks.thumbnails.cache', $this->config->get('webx-blocks.cache.enabled', true));
    }

    private function ttl(): int
    {
        return max(1, (int) $this->config->get('webx-blocks.thumbnails.ttl', 3600));
    }
}
