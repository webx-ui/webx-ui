<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\SiteUrl;

/**
 * The one place that reads the site's language strategy.
 *
 * Both strategies are here rather than only the one the rest of the suite runs under: the whole
 * point of moving this out of `HasUrl` was that a second reader would drift, and a test that
 * only ever sees `prefix` would not notice a second reader either.
 */
class SiteUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `app.url` alone does not reach a generator that was already built, and every
        // assertion here is about what stands after the host.
        $this->app['url']->forceRootUrl('https://example.test');
        $this->app['url']->forceScheme('https');
    }

    #[Test]
    public function the_default_language_has_no_prefix(): void
    {
        $this->useLocales(['en', 'uk']);

        $this->assertSame('', $this->site()->prefix('en'));
        $this->assertSame('uk', $this->site()->prefix('uk'));
    }

    #[Test]
    public function prefix_default_gives_the_default_language_one_too(): void
    {
        $this->useLocales(['en', 'uk']);
        $this->app['config']->set('webx-localization.prefix_default', true);

        $this->assertSame('en', $this->site()->prefix('en'));
    }

    #[Test]
    public function no_strategy_puts_the_language_nowhere(): void
    {
        $this->useLocales(['en', 'uk']);
        $this->app['config']->set('webx-localization.strategy', 'header');

        $this->assertSame('', $this->site()->prefix('uk'));
        $this->assertSame('https://example.test/account', $this->site()->to('/account', 'uk'));
    }

    #[Test]
    public function a_path_gets_the_prefix_of_the_language_it_is_read_in(): void
    {
        $this->useLocales(['en', 'uk']);

        $this->assertSame('https://example.test/uk/account', $this->site()->to('/account', 'uk'));
        $this->assertSame('https://example.test/account', $this->site()->to('account', 'en'));
    }

    #[Test]
    public function the_current_language_is_the_default_answer(): void
    {
        $this->useLocales(['en', 'uk']);
        $this->app->setLocale('uk');

        $this->assertSame('https://example.test/uk/account', $this->site()->to('/account'));
    }

    #[Test]
    public function the_site_root_is_the_prefix_alone(): void
    {
        $this->useLocales(['en', 'uk']);

        $this->assertSame('https://example.test/uk', $this->site()->to('/', 'uk'));
        $this->assertSame('https://example.test', $this->site()->to('/', 'en'));
    }

    /** §2, decision 7: an editor who prefixed it themselves does not get a second one. */
    #[Test]
    public function a_path_that_already_names_a_language_is_left_alone(): void
    {
        $this->useLocales(['en', 'uk']);

        $this->assertSame('https://example.test/uk/account', $this->site()->to('/uk/account', 'uk'));
        // Another language of the same site: a deliberate link, not a mistake to correct.
        $this->assertSame('https://example.test/en/account', $this->site()->to('/en/account', 'uk'));
    }

    #[Test]
    public function a_segment_that_merely_looks_like_a_language_is_prefixed(): void
    {
        $this->useLocales(['en', 'uk']);

        // `de` is not one of this site's languages, so it is an ordinary first segment.
        $this->assertSame('https://example.test/uk/de/parts', $this->site()->to('/de/parts', 'uk'));
    }

    #[Test]
    public function an_absolute_address_is_never_touched(): void
    {
        $this->useLocales(['en', 'uk']);

        foreach (['https://other.test/booking', 'http://other.test/', '//other.test/x', 'mailto:hi@other.test'] as $address) {
            $this->assertSame($address, $this->site()->to($address, 'uk'));
        }
    }

    /** The trait reads the same strategy through the same object — that is the whole point. */
    #[Test]
    public function an_entity_address_still_carries_the_prefix(): void
    {
        $this->useLocales(['en', 'uk']);

        $page = $this->page('about');

        $this->assertSame('https://example.test/about', $page->url('en'));
        $this->assertSame('https://example.test/uk/about', $page->url('uk'));
    }

    private function site(): SiteUrl
    {
        return $this->app->make(SiteUrl::class);
    }
}
