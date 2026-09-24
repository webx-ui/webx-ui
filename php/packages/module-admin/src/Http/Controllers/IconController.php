<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The panel's icons, served from the package under the panel's own prefix — so that a site
 * gets them with `composer update` rather than a `vendor:publish` somebody has to remember, and
 * so that they never compete with the site's own `/favicon.ico`.
 *
 * `webx-admin.icons` points at a directory with the same file names for a site that wants the
 * panel to carry its own mark; a file missing there falls back to the package's.
 */
final class IconController
{
    /** The files there are, and what they are. Nothing else under these names is served. */
    public const FILES = [
        'favicon.ico' => 'image/x-icon',
        'favicon-96x96.png' => 'image/png',
        'apple-touch-icon.png' => 'image/png',
        'web-app-manifest-192x192.png' => 'image/png',
        'web-app-manifest-512x512.png' => 'image/png',
    ];

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function __invoke(string $file): BinaryFileResponse
    {
        if (! isset(self::FILES[$file])) {
            throw new NotFoundHttpException;
        }

        $own = rtrim((string) $this->config->get('webx-admin.icons'), '/\\');
        $path = $own !== '' && is_file($own.'/'.$file) ? $own.'/'.$file : __DIR__.'/../../../resources/icons/'.$file;

        return (new BinaryFileResponse($path, 200, ['Content-Type' => self::FILES[$file]]))
            ->setPublic()
            ->setMaxAge(86400)
            ->setAutoEtag();
    }

    /**
     * What a browser installs the panel from. Written here rather than shipped as a file,
     * because its name is the panel's title and its scope is wherever the panel lives.
     */
    public function manifest(): JsonResponse
    {
        $title = (string) $this->config->get('webx-admin.title');
        $base = '/'.trim((string) $this->config->get('webx-admin.path'), '/').'/';
        $icon = static fn (int $size): array => [
            'src' => $base."web-app-manifest-{$size}x{$size}.png",
            'sizes' => "{$size}x{$size}",
            'type' => 'image/png',
            'purpose' => 'maskable',
        ];

        return new JsonResponse([
            'name' => $title,
            'short_name' => $title,
            'start_url' => $base,
            'scope' => $base,
            'icons' => [$icon(192), $icon(512)],
            'theme_color' => '#ffffff',
            'background_color' => '#ffffff',
            'display' => 'standalone',
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
