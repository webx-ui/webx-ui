<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Gallery\Video;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * YouTube, the one provider of the core (decision 2 of the video spec).
 *
 * Every form a link is shared in — `watch?v=`, `youtu.be/`, `/shorts/`, `/embed/`, `/live/`, on
 * `www.`, `m.` and `music.` — and nothing that merely looks like one: another host, a channel, a
 * playlist or an id that is not eleven characters is not a video.
 *
 * Played from `youtube-nocookie.com`, so that a page sets no YouTube cookie until the reader
 * presses play (decision 8). The title comes from oEmbed, which needs no key; the duration would
 * need the Data API and is left unknown.
 */
final class YouTubeProvider implements VideoProvider
{
    private const ID = '/^[A-Za-z0-9_-]{11}$/';

    private const HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];

    private const PATHS = ['shorts', 'embed', 'live', 'v'];

    public function key(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube';
    }

    public function idFrom(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) !== 1) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $segments = array_values(array_filter(explode('/', $parts['path'] ?? ''), static fn (string $one): bool => $one !== ''));

        if ($host === 'youtu.be' || $host === 'www.youtu.be') {
            return self::id($segments[0] ?? null);
        }

        if (! in_array($host, self::HOSTS, true)) {
            return null;
        }

        if (($segments[0] ?? null) === 'watch' && count($segments) === 1) {
            parse_str($parts['query'] ?? '', $query);

            return self::id($query['v'] ?? null);
        }

        if (in_array($segments[0] ?? null, self::PATHS, true)) {
            return self::id($segments[1] ?? null);
        }

        return null;
    }

    public function embedUrl(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.rawurlencode($id);
    }

    public function watchUrl(string $id): string
    {
        return 'https://www.youtube.com/watch?v='.rawurlencode($id);
    }

    public function posterUrls(string $id): array
    {
        return [
            'https://i.ytimg.com/vi/'.rawurlencode($id).'/maxresdefault.jpg',
            'https://i.ytimg.com/vi/'.rawurlencode($id).'/hqdefault.jpg',
        ];
    }

    public function title(string $id): ?string
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get('https://www.youtube.com/oembed', [
                'url' => $this->watchUrl($id),
                'format' => 'json',
            ]);
        } catch (Throwable) {
            return null;
        }

        $title = $response->successful() ? $response->json('title') : null;

        return is_string($title) && trim($title) !== '' ? trim($title) : null;
    }

    private static function id(mixed $candidate): ?string
    {
        return is_string($candidate) && preg_match(self::ID, $candidate) === 1 ? $candidate : null;
    }
}
