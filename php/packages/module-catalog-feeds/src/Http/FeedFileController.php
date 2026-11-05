<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WebxUi\CatalogFeeds\Delivery\FeedFiles;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;

/**
 * `GET /feeds/{key}.{ext}` (§7.5 of the feeds spec): the file, ready. A request never builds.
 *
 * A wrong key, an old key, a feed switched off and a feed without a file are one and the same 404
 * to a stranger; only whoever holds the right key is told to come back.
 */
final class FeedFileController
{
    /** Long enough for a build on a live queue, short enough for a marketplace to try again. */
    private const RETRY_SECONDS = 600;

    private const HEADERS = ['X-Robots-Tag' => 'noindex', 'Cache-Control' => 'private, max-age=0, must-revalidate'];

    public function __construct(private readonly FeedFiles $files) {}

    public function __invoke(Request $request, string $key, string $extension): Response
    {
        $feed = Feed::query()->where('access_key', $key)->first(['id', 'access_key', 'format', 'driver', 'is_active', 'current_build_id']);

        // Compared again here: a collation of the database may not tell `aB` from `Ab`.
        if (! $feed instanceof Feed || ! hash_equals($feed->access_key, $key) || ! $feed->is_active) {
            return $this->missing();
        }

        if ($extension !== $feed->extension()) {
            return new RedirectResponse($feed->address(), 301, self::HEADERS);
        }

        $build = $feed->currentBuild;

        // The format changed and nothing was built since: what is there is not what the address says.
        if (! $build instanceof FeedBuild || ! str_ends_with((string) $build->path, '.'.$extension)) {
            return $this->missing(retry: true);
        }

        $response = new Response(headers: [...self::HEADERS, 'Content-Type' => $build->content_type ?? Feed::TYPES[$feed->format].'; charset=utf-8']);
        $response->setEtag((string) $build->etag);
        $response->setLastModified($build->content_at);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $response->headers->set('Content-Length', (string) $build->bytes);

        return $request->isMethod('HEAD') ? $response : $this->stream($build, $response);
    }

    private function stream(FeedBuild $build, Response $response): Response
    {
        $stream = $this->files->disk()->readStream((string) $build->path);

        if (! is_resource($stream)) {
            return $this->missing(retry: true);
        }

        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, $response->headers->all());
    }

    private function missing(bool $retry = false): Response
    {
        return new Response('', 404, [...self::HEADERS, ...($retry ? ['Retry-After' => self::RETRY_SECONDS] : [])]);
    }
}
