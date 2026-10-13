<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\SiteBar\SiteBar;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Facades\Preview;

/**
 * The panel on the site (WEBX_UI_SITE_BAR.md, §3, §6, §11), on a real page of the address
 * registry — the page a section's record answers at is what the bar is about.
 *
 * Here rather than in `module-admin`, which owns the bar: the frame's own tests have neither an
 * administrator nor an address, and the decision is about both.
 */
final class SiteBarTest extends TestCase
{
    #[Test]
    public function a_visitor_gets_the_page_byte_for_byte_as_without_the_bar(): void
    {
        $this->page('pricing');

        $with = $this->get('/pricing')->assertOk();

        $this->app['config']->set('webx-admin.site_bar.enabled', false);
        $without = $this->get('/pricing')->assertOk();

        $this->assertSame($without->getContent(), $with->getContent());
        $this->assertSame($this->headers($without), $this->headers($with));
        $this->assertStringNotContainsString('data-wx-bar', (string) $with->getContent());
        $this->assertStringNotContainsString('<!--wx:', (string) $with->getContent());
        $this->assertStringNotContainsString('no-store', (string) $with->headers->get('Cache-Control'));
        $this->assertNotContains('Cookie', $with->headers->all('vary'));
    }

    #[Test]
    public function an_administrator_gets_one_script_before_the_end_of_the_body(): void
    {
        $this->page('pricing');
        $this->signIn($this->editor());

        $response = $this->get('/pricing')->assertOk();
        $html = (string) $response->getContent();

        $this->assertSame(1, substr_count($html, '<script type="module" src="/cms/_bar/loader.'));
        $this->assertMatchesRegularExpression('~<script type="module" src="/cms/_bar/loader\.[a-f0-9]{12}\.js" data-wx-bar="[^"]+"></script>\s*</body>~', $html);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertContains('Cookie', $response->headers->all('vary'));

        preg_match('~data-wx-bar="([^"]+)"~', $html, $match);
        $data = json_decode(html_entity_decode($match[1]), true);

        $this->assertSame('/cms/_bar', $data['src']);
        $this->assertSame('Open the site panel', $data['words']['open']);
    }

    #[Test]
    public function the_script_it_names_is_served(): void
    {
        $this->page('pricing');
        $this->signIn($this->editor());

        preg_match('~src="(/cms/_bar/loader\.[a-f0-9]+\.js)"~', (string) $this->get('/pricing')->getContent(), $match);

        $this->get($match[1])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8')
            ->assertSee(':host', false);
    }

    #[Test]
    public function the_words_are_in_the_administrators_language(): void
    {
        $this->page('pricing');
        $admin = $this->editor();
        $admin->forceFill(['locale' => 'ru'])->save();
        $this->app['config']->set('webx-localization.panel_locales', ['en', 'ru']);
        $this->signIn($admin);

        $html = (string) $this->get('/pricing')->getContent();

        $this->assertStringContainsString('Открыть панель сайта', html_entity_decode($html));
    }

    #[Test]
    public function no_bar_for_an_administrator_who_may_not_open_the_record(): void
    {
        $this->page('pricing');
        $this->signIn($this->editor(['pages.view']));

        $this->assertNoBar($this->get('/pricing')->assertOk());
    }

    #[Test]
    public function no_bar_for_a_switched_off_account(): void
    {
        $this->page('pricing');
        $admin = $this->editor();
        $admin->forceFill(['is_active' => false])->save();
        $this->signIn($admin);

        $this->assertNoBar($this->get('/pricing'));
    }

    #[Test]
    public function no_bar_for_somebody_who_turned_it_off(): void
    {
        $this->page('pricing');
        $admin = $this->editor();
        $admin->forceFill(['site_bar' => false])->save();
        $this->signIn($admin->fresh() ?? $admin);

        $this->assertNoBar($this->get('/pricing')->assertOk());
    }

    #[Test]
    public function no_bar_when_the_site_switched_it_off(): void
    {
        $this->page('pricing');
        $this->signIn($this->editor());
        $this->app['config']->set('webx-admin.site_bar.enabled', false);

        $this->assertNoBar($this->get('/pricing')->assertOk());
    }

    #[Test]
    public function no_bar_inside_a_frame(): void
    {
        $this->page('pricing');
        $this->signIn($this->editor());

        $this->assertNoBar($this->get('/pricing', ['Sec-Fetch-Dest' => 'iframe', 'Sec-Fetch-Site' => 'same-origin'])->assertOk());
    }

    #[Test]
    public function no_bar_on_a_redirect_a_missing_page_or_json(): void
    {
        $page = $this->page('pricing');
        $page->forceFill(['slug' => 'prices'])->save();
        $this->signIn($this->editor());

        Route::middleware('web')->get('/feed.json', static fn (): array => ['body' => '</body>']);

        $this->assertNoBar($this->get('/pricing')->assertRedirect());
        $this->assertNoBar($this->get('/nowhere')->assertNotFound());
        $this->assertNoBar($this->get('/feed.json')->assertOk());
    }

    #[Test]
    public function no_bar_on_a_preview(): void
    {
        $page = $this->page('pricing', published: false);
        $page->saveDraft(['title' => ['en' => 'Pricing']]);
        $this->signIn($this->editor());

        // A preview is `no-store` of its own accord; the script is what must be missing.
        $this->assertStringNotContainsString('data-wx-bar', (string) $this->get(Preview::url($page))->assertOk()->getContent());
    }

    #[Test]
    public function a_page_without_a_body_is_left_alone(): void
    {
        $this->signIn($this->editor());

        Route::middleware('web')->get('/fragment', static fn (): string => '<p>Hello</p>');

        $response = $this->get('/fragment')->assertOk();

        $this->assertSame('<p>Hello</p>', $response->getContent());
        $this->assertNoBar($response);
    }

    #[Test]
    public function the_context_names_the_record_behind_the_address(): void
    {
        $page = $this->page('pricing');
        $this->signIn($this->editor());

        $this->getJson('/api/cms/site-bar/context?url=/pricing')
            ->assertOk()
            ->assertJsonPath('data.url', '/pricing')
            ->assertJsonPath('data.entity.type', 'page')
            ->assertJsonPath('data.entity.id', $page->getKey())
            ->assertJsonPath('data.entity.kind', 'Page')
            ->assertJsonPath('data.entity.label', 'Pricing')
            ->assertJsonPath('data.entity.status', 'published')
            ->assertJsonPath('data.entity.edit', '/pages/'.$page->getKey())
            ->assertJsonPath('data.entity.draft.differs', false)
            ->assertJsonPath('data.entity.can.edit', true)
            ->assertJsonPath('data.sections', ['page', 'seo']);
    }

    #[Test]
    public function the_context_says_who_left_a_draft(): void
    {
        $page = $this->page('pricing');
        $admin = $this->editor();
        $page->saveDraft(['title' => ['en' => 'New pricing']], $admin->getKey());
        $this->signIn($admin);

        $this->getJson('/api/cms/site-bar/context?url=/pricing?utm=1')
            ->assertOk()
            ->assertJsonPath('data.url', '/pricing')
            ->assertJsonPath('data.entity.draft.differs', true)
            ->assertJsonPath('data.entity.draft.by', 'Editor');
    }

    #[Test]
    public function an_address_without_a_record_has_no_entity_but_keeps_the_other_tabs(): void
    {
        // What the SEO tab will be on SB2: something for any address, record or not.
        $this->app->make(SiteBar::class)->section('everywhere', static fn (): bool => true);
        $this->signIn($this->editor());

        $this->getJson('/api/cms/site-bar/context?url=/contacts')
            ->assertOk()
            ->assertJsonPath('data.entity', null)
            ->assertJsonPath('data.sections', ['everywhere']);
    }

    #[Test]
    public function an_address_with_nothing_to_show_is_refused(): void
    {
        $this->signIn($this->editor());

        $this->getJson('/api/cms/site-bar/context?url=/contacts')->assertForbidden();
    }

    #[Test]
    public function a_record_without_the_right_is_not_named(): void
    {
        $this->page('pricing');
        $this->signIn($this->editor(['pages.view']));

        $response = $this->getJson('/api/cms/site-bar/context?url=/pricing')->assertForbidden();

        $this->assertStringNotContainsString('Pricing', (string) $response->getContent());
    }

    #[Test]
    public function only_an_address_of_this_site_is_looked_up(): void
    {
        $this->page('pricing');
        $this->signIn($this->editor());

        foreach (['https://elsewhere.test/pricing', '//elsewhere.test/pricing', '/\\elsewhere.test', 'pricing', 'javascript:alert(1)'] as $url) {
            $this->getJson('/api/cms/site-bar/context?url='.rawurlencode($url))
                ->assertStatus(422)
                ->assertJsonValidationErrors('url');
        }
    }

    #[Test]
    public function a_visitor_cannot_ask(): void
    {
        $this->getJson('/api/cms/site-bar/context?url=/pricing')->assertUnauthorized();
    }

    #[Test]
    public function the_screen_is_framed_by_the_site_only(): void
    {
        $this->get('/cms/_bar')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    private function signIn(CmsUser $admin): void
    {
        // By name, as a real session would: `actingAs()` would also make `cms` the default
        // guard, which is exactly what a page of the site does not have.
        $this->app['auth']->guard('cms')->setUser($admin);
    }

    /** @param  TestResponse<Response>  $response */
    private function assertNoBar(TestResponse $response): void
    {
        $this->assertStringNotContainsString('data-wx-bar', (string) $response->getContent());
        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * Every header but the two that differ between any two requests: the date, and the session
     * cookie a fresh visitor is given each time.
     *
     * @param  TestResponse<Response>  $response
     * @return array<string, list<string|null>>
     */
    private function headers(TestResponse $response): array
    {
        $headers = $response->headers->all();
        unset($headers['date'], $headers['set-cookie']);
        ksort($headers);

        return $headers;
    }
}
