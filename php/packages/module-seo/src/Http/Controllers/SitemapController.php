<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Seo\Sitemap\Sitemap;

/** `/sitemap.xml` and the files it names (§17.3). */
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

    private function xml(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
