<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Rendering\Assets;

/**
 * `{script_path}/step.js` and `methods.js` — the scripts of the payment pages (spec §10.3, groups
 * spec §6.5). Immutable: the address carries a hash of the contents, so a release that changes a
 * script changes its address.
 */
final class ScriptController
{
    public function __invoke(): Response
    {
        return $this->file(Assets::SCRIPT);
    }

    public function methods(): Response
    {
        return $this->file(Assets::METHODS);
    }

    private function file(string $file): Response
    {
        return new Response(Assets::contents($file), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.Assets::version($file).'"',
        ]);
    }
}
