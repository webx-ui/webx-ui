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
 *
 * A list reads them in one go ({@see self::prime()}): the generation once, the keys with one
 * `many()`. Read type by type it was two reads per card, ninety for one `GET /api/cms/blocks` on
 * a site with forty-odd types. And moving the generation on deletes what the old one wrote,
 * through a list of its keys: left to expire, a `database` store kept every old row — it expires
 * entries on read, and nobody read them again.
 *
 * Bound per request (`scoped`), so the generation read once is not carried into the next one.
 */
final class Thumbnails
{
    private const GENERATION = 'webx.blocks.thumbnails.generation';

    private const INDEX = 'webx.blocks.thumbnails.index.';

    private ?string $generation = null;

    /** @var array<string, array{html: string, styles: string, empty: bool}> Read or drawn this request, by cache key. */
    private array $kept = [];

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
            $key = $this->key($type);

            if (! isset($this->kept[$key])) {
                $this->prime([$type]);
            }

            return $this->kept[$key] ?? $this->draw($type);
        } catch (Throwable) {
            return $this->draw($type);
        }
    }

    /**
     * The thumbnails of many types at once, for a list: what is kept read with one `many()`, the
     * rest drawn and written with one `putMany()`. {@see self::of()} then answers from memory.
     *
     * @param  iterable<BlockType>  $types
     */
    public function prime(iterable $types): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $wanted = [];

            foreach ($types as $type) {
                $key = $this->key($type);

                if (! isset($this->kept[$key])) {
                    $wanted[$key] = $type;
                }
            }

            if ($wanted === []) {
                return;
            }

            $drawn = [];

            foreach ($this->cache->many(array_keys($wanted)) as $key => $value) {
                if (is_array($value)) {
                    /** @var array{html: string, styles: string, empty: bool} $value */
                    $this->kept[(string) $key] = $value;
                } else {
                    $drawn[(string) $key] = $this->kept[(string) $key] = $this->draw($wanted[$key]);
                }
            }

            if ($drawn !== []) {
                $this->cache->putMany($drawn, $this->ttl());
                $this->remember(array_keys($drawn));
            }
        } catch (Throwable) {
            // No cache to read: of() draws each one.
        }
    }

    /** Every thumbnail drawn so far is stale: the next request draws them again. */
    public function forget(): void
    {
        try {
            $old = $this->cache->get(self::GENERATION);
            $this->generation = Str::random(8);
            $this->kept = [];
            $this->cache->forever(self::GENERATION, $this->generation);

            if (is_string($old) && $old !== '') {
                $keys = $this->cache->get(self::INDEX.$old);
                $this->cache->deleteMultiple([...(is_array($keys) ? $keys : []), self::INDEX.$old]);
            }
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

    private function key(BlockType $type): string
    {
        return sprintf('webx.blocks.thumbnails.%s.%d.%s', $this->generation(), $type->versionId, $this->locales->content());
    }

    /**
     * The keys this generation wrote, so that moving it on can delete them.
     *
     * @param  list<string>  $keys
     */
    private function remember(array $keys): void
    {
        $index = self::INDEX.$this->generation();
        $known = $this->cache->get($index);

        $this->cache->forever($index, array_values(array_unique([...(is_array($known) ? $known : []), ...$keys])));
    }

    private function generation(): string
    {
        if ($this->generation !== null) {
            return $this->generation;
        }

        $generation = $this->cache->get(self::GENERATION);

        if (! is_string($generation) || $generation === '') {
            $generation = Str::random(8);
            $this->cache->forever(self::GENERATION, $generation);
        }

        return $this->generation = $generation;
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
