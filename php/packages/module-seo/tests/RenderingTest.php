<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Settings\Settings;

final class RenderingTest extends TestCase
{
    #[Test]
    public function the_head_carries_what_the_rule_says(): void
    {
        SeoUrl::query()->create([
            'match_type' => 'exact',
            'pattern' => '/about',
            'title' => ['ru' => 'О нас'],
            'description' => ['ru' => 'Кто мы такие'],
            'robots' => 'noindex, nofollow',
            'canonical' => 'https://example.test/about',
        ]);

        $head = (string) app(Seo::class)->head(null, '/about', 'ru');

        $this->assertStringContainsString('<title>О нас</title>', $head);
        $this->assertStringContainsString('<meta name="description" content="Кто мы такие">', $head);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $head);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.test/about">', $head);
        $this->assertStringContainsString('<meta property="og:title" content="О нас">', $head);
    }

    #[Test]
    public function a_quote_in_a_title_cannot_end_the_attribute_it_is_in(): void
    {
        // Without this a title somebody pasted from a document takes the page's markup with it.
        SeoUrl::query()->create([
            'match_type' => 'exact',
            'pattern' => '/about',
            'title' => ['ru' => 'Кавычка " и <script>alert(1)</script>'],
        ]);

        $head = (string) app(Seo::class)->head(null, '/about', 'ru');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $head);
        $this->assertStringContainsString('&quot;', $head);
        $this->assertStringContainsString('&lt;script&gt;', $head);
    }

    #[Test]
    public function structured_data_cannot_close_its_own_script_tag(): void
    {
        app(Settings::class)->save(['seo.org-name' => ['ru' => 'Acme </script><script>alert(1)</script>']]);

        $head = (string) app(Seo::class)->head(null, '/', 'ru');

        $this->assertStringContainsString('application/ld+json', $head);
        $this->assertStringNotContainsString('</script><script>alert(1)', $head);
        // JSON_HEX_TAG, so the angle brackets survive as escapes a parser still reads back.
        $this->assertStringContainsString('<', $head);
    }

    #[Test]
    public function the_directive_prints_the_same_block(): void
    {
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/about', 'title' => ['ru' => 'О нас']]);

        // By path rather than by name: the fixture lives next to this test, not in a view
        // directory the application knows about.
        Route::get('/about', fn (): string => view()->file(__DIR__.'/Fixtures/views/seo-test-page.blade.php')->render());

        $this->get('/about')->assertOk()->assertSee('<title>О нас</title>', false);
    }
}
