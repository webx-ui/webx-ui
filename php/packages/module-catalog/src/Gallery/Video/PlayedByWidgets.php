<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Gallery\Video;

use Closure;
use WebxUi\Widgets\Video\ProvidedVideo;
use WebxUi\Widgets\Video\VideoProvider as PlayedProvider;

/**
 * A provider of the gallery, played on the storefront by `<x-webx-video>` (§6 of the video spec).
 *
 * Two registries, two questions: the catalog's {@see VideoProviders} decides what a gallery row
 * stores — the key, the id, the cover a new row is made of — and the widgets' decides how a
 * page plays it, behind the visitor's consent. YouTube is in both; a provider a satellite or a
 * site registers only in the catalog is handed to the widgets through this, so its videos play
 * on the product page as they did before, now waiting for consent like every other.
 */
final readonly class PlayedByWidgets implements PlayedProvider
{
    public function __construct(private VideoProvider $provider) {}

    public function key(): string
    {
        return $this->provider->key();
    }

    public function label(): string
    {
        return $this->provider->label();
    }

    public function find(string $url): ?ProvidedVideo
    {
        $id = $this->provider->idFrom($url);

        return $id === null ? null : new ProvidedVideo($this, $id);
    }

    /** The catalog's embed address plays on load: it is put in only on a click. */
    public function embed(ProvidedVideo $video): string
    {
        $embed = $this->provider->embedUrl($video->id);

        return $embed.(str_contains($embed, '?') ? '&' : '?').'autoplay=1';
    }

    public function page(ProvidedVideo $video): string
    {
        return $this->provider->watchUrl($video->id);
    }

    public function preview(ProvidedVideo $video, Closure $fetch): ?string
    {
        foreach ($this->provider->posterUrls($video->id) as $url) {
            if (($bytes = $fetch($url)) !== null) {
                return $bytes;
            }
        }

        return null;
    }
}
