<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pages in the sitemap (§17.5 of the SEO spec): published and out of the bin, the same answer
 * the handler gives.
 */
final class SitemapTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
    }

    #[Test]
    public function a_published_page_is_in_the_map_and_a_draft_is_not(): void
    {
        $this->home()->publish();
        $this->page('about');
        $this->page('draft', published: false);

        $map = $this->map();

        $this->assertStringContainsString('<loc>http://localhost/about</loc>', $map);
        $this->assertStringNotContainsString('draft', $map);
        $this->get('/draft')->assertNotFound();
    }

    #[Test]
    public function publishing_a_page_puts_it_in_the_map_already_built(): void
    {
        $this->page('about');
        $draft = $this->page('later', published: false);

        $this->assertStringNotContainsString('/later<', $this->map());

        $draft->publish();

        $this->assertStringContainsString('/later<', $this->map());
    }

    #[Test]
    public function a_page_in_the_bin_is_not_in_the_map(): void
    {
        $this->page('about');
        $this->page('gone')->delete();

        $this->assertStringNotContainsString('gone', $this->map());
    }

    private function map(): string
    {
        $response = $this->get('/sitemap-page.xml');

        return $response->getStatusCode() === 404 ? '' : (string) $response->getContent();
    }
}
