<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Seo\Models\SeoUrl;

/**
 * The SEO tab of the panel on the site (WEBX_UI_SITE_BAR.md, §7, §11).
 *
 * Here rather than in `module-seo`, beside the frame's own tests: the tab is about the record
 * behind a real address, and a page of this module is the record the bar was drawn for first —
 * with its card (`HasSeo`), its section's right and its row in the address registry.
 */
final class SiteBarSeoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A page of the site's own code: no record behind it, and the rule is all SEO it can have.
        Route::middleware('web')->get('/contacts', static fn (): string => '<html><head><title>Contacts</title></head><body><p>Call us</p></body></html>');
    }

    #[Test]
    public function the_tab_is_there_for_whoever_may_save_the_records_card(): void
    {
        $this->page('pricing');
        $this->signIn($this->editor());

        $this->getJson('/api/cms/site-bar/context?url=/pricing')
            ->assertOk()
            ->assertJsonPath('data.sections', ['page', 'seo']);
    }

    #[Test]
    public function a_record_is_edited_in_its_own_card(): void
    {
        $page = $this->page('pricing');
        $page->saveSeo(['description' => ['en' => 'Plans and prices'], 'og_title' => ['en' => 'Share me']]);
        $this->signIn($this->editor());

        $this->getJson('/api/cms/seo/site-bar?url=/pricing')
            ->assertOk()
            ->assertJsonPath('data.target', 'entity')
            ->assertJsonPath('data.entity.type', 'page')
            ->assertJsonPath('data.entity.id', $page->getKey())
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.fields.description.own', 'Plans and prices')
            ->assertJsonPath('data.fields.description.source', 'entity')
            ->assertJsonPath('data.fields.title.own', null)
            // Nobody wrote a title: the page is called by its own name.
            ->assertJsonPath('data.fields.title.source', 'fallback')
            ->assertJsonPath('data.fields.title.overridden', null)
            ->assertJsonPath('data.title', null);

        $this->putJson('/api/cms/seo/entity/page/'.$page->getKey(), [
            'seo' => ['title' => ['en' => 'Pricing that fits'], 'robots' => 'noindex'],
        ])->assertOk()->assertJsonPath('data.seo.title.en', 'Pricing that fits');

        $value = $page->refresh()->seoValue();

        $this->assertSame(['en' => 'Pricing that fits'], $value['title']);
        $this->assertSame('noindex', $value['robots']);
        // Only what was named: the rest of the card is as it was.
        $this->assertSame(['en' => 'Plans and prices'], $value['description']);
        $this->assertSame(['en' => 'Share me'], $value['og_title']);
        // And not into a draft: SEO is saved the moment it is saved.
        $this->assertFalse($page->hasDraft() && $page->changedFields() !== []);

        $this->getJson('/api/cms/seo/site-bar?url=/pricing')
            ->assertOk()
            ->assertJsonPath('data.fields.title.source', 'entity')
            ->assertJsonPath('data.fields.title.own', 'Pricing that fits')
            ->assertJsonPath('data.fields.robots.value', 'noindex')
            ->assertJsonPath('data.title', 'Pricing that fits');
    }

    #[Test]
    public function null_empties_one_field_of_the_card(): void
    {
        $page = $this->page('pricing');
        $page->saveSeo(['title' => ['en' => 'Old'], 'description' => ['en' => 'Kept']]);
        $this->signIn($this->editor());

        $this->putJson('/api/cms/seo/entity/page/'.$page->getKey(), ['seo' => ['title' => ['en' => null]]])->assertOk();

        $value = $page->refresh()->seoValue();

        $this->assertArrayNotHasKey('title', $value);
        $this->assertSame(['en' => 'Kept'], $value['description']);
    }

    #[Test]
    public function a_value_the_card_refuses_is_refused_here_too(): void
    {
        $page = $this->page('pricing');
        $this->signIn($this->editor());

        $this->putJson('/api/cms/seo/entity/page/'.$page->getKey(), ['seo' => ['title' => ['en' => str_repeat('a', 300)]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('seo');

        $this->assertSame([], $page->refresh()->seoValue());
    }

    #[Test]
    public function a_field_a_rule_sets_is_flagged_before_anything_is_saved(): void
    {
        $page = $this->page('pricing');
        $page->saveSeo(['title' => ['en' => 'From the page']]);
        $rule = SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/pricing', 'title' => ['en' => 'From the rule']]);
        $this->signIn($this->editor());

        $this->getJson('/api/cms/seo/site-bar?url=/pricing')
            ->assertOk()
            ->assertJsonPath('data.target', 'entity')
            ->assertJsonPath('data.fields.title.source', 'rule')
            ->assertJsonPath('data.fields.title.own', 'From the page')
            ->assertJsonPath('data.fields.title.overridden', 'rule')
            // Only the field the rule fills in: the description below it is still the page's.
            ->assertJsonPath('data.fields.description.overridden', null)
            ->assertJsonPath('data.rule.id', $rule->id)
            ->assertJsonPath('data.rule.pattern', '/pricing')
            ->assertJsonPath('data.rule.exact', true)
            ->assertJsonPath('data.title', 'From the rule');
    }

    #[Test]
    public function an_address_without_a_record_is_edited_in_a_rule(): void
    {
        $this->signIn($this->editor(['seo.view', 'seo.manage']));

        $this->getJson('/api/cms/site-bar/context?url=/contacts')
            ->assertOk()
            ->assertJsonPath('data.entity', null)
            ->assertJsonPath('data.sections', ['seo']);

        $this->getJson('/api/cms/seo/site-bar?url=/contacts')
            ->assertOk()
            ->assertJsonPath('data.target', 'rule')
            ->assertJsonPath('data.entity', null)
            ->assertJsonPath('data.rule', null)
            ->assertJsonPath('data.can.rule', true);

        // The tab writes through the rules' own address, the one the SEO section uses.
        $this->postJson('/api/cms/seo/urls', [
            'match_type' => 'exact',
            'pattern' => '/contacts',
            'title' => ['en' => 'Talk to us'],
        ])->assertCreated();

        $this->getJson('/api/cms/seo/site-bar?url=/contacts')
            ->assertOk()
            ->assertJsonPath('data.rule.exact', true)
            ->assertJsonPath('data.fields.title.own', 'Talk to us')
            ->assertJsonPath('data.fields.title.source', 'rule')
            ->assertJsonPath('data.fields.title.overridden', null);
    }

    #[Test]
    public function a_mask_is_the_starting_point_of_a_rule_for_this_address(): void
    {
        $mask = SeoUrl::query()->create(['match_type' => 'mask', 'pattern' => '/con*', 'description' => ['en' => 'Everything under con']]);
        $this->signIn($this->editor(['seo.manage']));

        $this->getJson('/api/cms/seo/site-bar?url=/contacts')
            ->assertOk()
            ->assertJsonPath('data.rule.id', $mask->id)
            ->assertJsonPath('data.rule.exact', false)
            ->assertJsonPath('data.fields.description.own', 'Everything under con')
            ->assertJsonPath('data.fields.description.overridden', null);
    }

    #[Test]
    public function an_administrator_with_seo_but_not_the_section_writes_a_rule_not_the_card(): void
    {
        $page = $this->page('pricing');
        $this->signIn($this->editor(['seo.manage']));

        $this->getJson('/api/cms/site-bar/context?url=/pricing')
            ->assertOk()
            ->assertJsonPath('data.entity', null)
            ->assertJsonPath('data.sections', ['seo']);

        $this->getJson('/api/cms/seo/site-bar?url=/pricing')
            ->assertOk()
            ->assertJsonPath('data.target', 'rule')
            ->assertJsonPath('data.entity', null);

        $this->putJson('/api/cms/seo/entity/page/'.$page->getKey(), ['seo' => ['title' => ['en' => 'Nope']]])->assertForbidden();
        $this->assertSame([], $page->refresh()->seoValue());
    }

    #[Test]
    public function nobody_without_a_right_reads_or_writes(): void
    {
        $page = $this->page('pricing');
        $this->signIn($this->editor(['pages.view', 'seo.view']));

        $this->getJson('/api/cms/seo/site-bar?url=/pricing')->assertForbidden();
        $this->getJson('/api/cms/seo/site-bar?url=/contacts')->assertForbidden();
        $this->putJson('/api/cms/seo/entity/page/'.$page->getKey(), ['seo' => ['title' => ['en' => 'Nope']]])->assertForbidden();

        $this->assertSame([], $page->refresh()->seoValue());
    }

    #[Test]
    public function an_unknown_record_or_type_is_not_found(): void
    {
        $this->signIn($this->editor());

        $this->putJson('/api/cms/seo/entity/page/9999', ['seo' => ['title' => ['en' => 'x']]])->assertNotFound();
        $this->putJson('/api/cms/seo/entity/nothing/1', ['seo' => ['title' => ['en' => 'x']]])->assertNotFound();
    }

    #[Test]
    public function only_a_path_of_this_site_is_read(): void
    {
        $this->signIn($this->editor(['seo.manage']));

        foreach (['https://elsewhere.test/contacts', '//elsewhere.test/contacts', 'contacts'] as $url) {
            $this->getJson('/api/cms/seo/site-bar?url='.rawurlencode($url))
                ->assertStatus(422)
                ->assertJsonValidationErrors('url');
        }
    }

    #[Test]
    public function a_visitor_cannot_ask_or_write(): void
    {
        $page = $this->page('pricing');

        $this->getJson('/api/cms/seo/site-bar?url=/pricing')->assertUnauthorized();
        $this->putJson('/api/cms/seo/entity/page/'.$page->getKey(), ['seo' => ['title' => ['en' => 'x']]])->assertUnauthorized();
    }

    #[Test]
    public function an_address_without_a_record_gets_the_bar_for_seo(): void
    {
        $this->signIn($this->editor(['seo.manage']));

        $response = $this->get('/contacts')->assertOk();

        $this->assertStringContainsString('data-wx-bar', (string) $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function without_seo_an_address_without_a_record_has_no_bar(): void
    {
        $this->signIn($this->editor());

        $this->assertStringNotContainsString('data-wx-bar', (string) $this->get('/contacts')->assertOk()->getContent());
    }

    #[Test]
    public function a_visitor_gets_an_address_without_a_record_byte_for_byte(): void
    {
        $with = $this->get('/contacts')->assertOk();

        $this->app['config']->set('webx-admin.site_bar.enabled', false);
        $without = $this->get('/contacts')->assertOk();

        $this->assertSame($without->getContent(), $with->getContent());
        $this->assertSame($this->headers($without), $this->headers($with));
        $this->assertStringNotContainsString('data-wx-bar', (string) $with->getContent());
        $this->assertStringNotContainsString('no-store', (string) $with->headers->get('Cache-Control'));
        $this->assertNotContains('Cookie', $with->headers->all('vary'));
    }

    private function signIn(CmsUser $admin): void
    {
        // By name, as a real session would: the site's pages have their own default guard.
        $this->app['auth']->guard('cms')->setUser($admin);
    }

    /**
     * Every header but the date and the session cookie, which differ between any two requests.
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
