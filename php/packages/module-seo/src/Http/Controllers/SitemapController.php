<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Seo\Sitemap\Sitemap;

/** `/sitemap.xml`, the files it names (§17.3), and the stylesheet they point a browser to. */
final class SitemapController
{
    public function __construct(private readonly Sitemap $sitemap) {}

    public function index(): Response
    {
        return $this->xml($this->sitemap->index());
    }

    public function file(string $file): Response
    {
        $xml = $this->sitemap->file($file);

        return $xml === null ? new Response('', 404) : $this->xml($xml);
    }

    /**
     * A file of the package, not a view: it changes with a release, never with a save, so a
     * day of browser cache costs nothing. `text/xsl` is the type every browser that still
     * runs XSLT accepts for it.
     */
    public function stylesheet(): Response
    {
        return new Response((string) file_get_contents(__DIR__.'/../../../resources/sitemap.xsl'), 200, [
            'Content-Type' => 'text/xsl; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function xml(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
