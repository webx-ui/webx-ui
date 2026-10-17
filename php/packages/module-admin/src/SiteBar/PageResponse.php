<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteBar;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What counts as a page of the site — the one kind of response something may be put on.
 *
 * The panel on the site and the debug bar ask the same question, and the day they answer it
 * differently is the day one of them shows up on a preview or inside a JSON answer. So the rules
 * live here once: a `GET` of the `web` group outside the panel, its API and the previews, not
 * framed, answered with a 200 of HTML that has a `</body>`.
 */
final class PageResponse
{
    public function __construct(private readonly Config $config) {}

    /** The half the request alone answers: known before anything is rendered. */
    public function request(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        // A preview, or the bar's own screen in its frame, is already the panel looking at the
        // site. Any frame rather than only one of this origin: a page somebody else frames is
        // not a place to edit or debug it from either.
        if ($request->headers->get('Sec-Fetch-Dest') === 'iframe') {
            return false;
        }

        $route = $request->route();

        if (! $route instanceof Route || ! in_array('web', $route->gatherMiddleware(), true)) {
            return false;
        }

        return ! $this->isServicePath($request->path());
    }

    /** The whole question: the request, then the response it got. */
    public function page(Request $request, Response $response): bool
    {
        return $this->request($request) && $this->response($response);
    }

    /** A 200 of HTML with somewhere to put a tag — not a stream, a file or JSON. */
    public function response(Response $response): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse || $response instanceof JsonResponse) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')) {
            return false;
        }

        $content = $response->getContent();

        return is_string($content) && stripos($content, '</body>') !== false;
    }

    /** The panel, its JSON and the previews are the panel's own pages, not the site's. */
    public function isServicePath(string $path): bool
    {
        $prefixes = [
            (string) $this->config->get('webx-admin.path', 'cms'),
            (string) $this->config->get('webx-admin.api_path', 'api/cms'),
            (string) $this->config->get('webx-blocks.preview.path', '_preview'),
        ];

        foreach ($prefixes as $prefix) {
            $prefix = trim($prefix, '/');

            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return true;
            }
        }

        return false;
    }
}
