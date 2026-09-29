<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Gallery\Video;

/**
 * A place videos live, as the gallery sees it (§3 of the video spec): whether an address is one
 * of its videos, and the addresses to embed it, to show its cover and to send a person to.
 *
 * Registered in {@see VideoProviders}; a satellite or a site that registers one makes its links
 * accepted everywhere a YouTube link is, without touching the core.
 */
interface VideoProvider
{
    /** What `video_provider` holds for its videos: lowercase, at most 32 characters, not `file`. */
    public function key(): string;

    /** The name a person reads in the journal: "YouTube". */
    public function label(): string;

    /** The id of the video when the address is one of this provider's; null otherwise. */
    public function idFrom(string $url): ?string;

    /** The address an iframe plays, without autoplay. */
    public function embedUrl(string $id): string;

    /** The page of the video for a person, and the link a page without JS falls back to. */
    public function watchUrl(string $id): string;

    /**
     * Addresses of the cover, best first: the gallery takes the first that answers with a picture.
     *
     * @return list<string>
     */
    public function posterUrls(string $id): array;

    /** The title of the video, when the provider tells it without a key; null when it does not. */
    public function title(string $id): ?string;
}
