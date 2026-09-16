<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Blocks\Models\BlockBundle;
use WebxUi\Blocks\Rendering\Bundles;

/**
 * `/blocks/{hash}.css`, `/blocks/{hash}.js` and `/blocks/runtime.js`.
 *
 * Everything here is immutable by construction — the hash names the versions, the runtime's URL
 * carries its content hash — so every answer says so, and a browser never asks twice.
 */
final class BundleController
{
    private const IMMUTABLE = 'public, max-age=31536000, immutable';

    public function __construct(private readonly Bundles $bundles) {}

    public function css(string $hash): Response
    {
        $bundle = $this->bundles->find($hash);

        if (! $bundle instanceof BlockBundle) {
            return $this->missing();
        }

        return $this->serve($bundle->css, 'text/css');
    }

    public function js(string $hash): Response
    {
        $bundle = $this->bundles->find($hash);

        if (! $bundle instanceof BlockBundle || $bundle->js === null) {
            return $this->missing();
        }

        return $this->serve($bundle->js, 'text/javascript');
    }

    public function runtime(): Response
    {
        return $this->serve($this->bundles->runtime()."\n", 'text/javascript');
    }

    private function serve(string $body, string $type): Response
    {
        return new Response($body, 200, [
            'Content-Type' => $type.'; charset=UTF-8',
            'Cache-Control' => self::IMMUTABLE,
        ]);
    }

    private function missing(): Response
    {
        // A 404 for a hash nobody wrote must not be cached as long as a hit would be: the
        // bundle may be written a moment later, when the page that names it is rendered.
        return new Response('', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
