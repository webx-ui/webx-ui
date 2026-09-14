<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Models\SeoUrl;

final class SeoEndpointsTest extends TestCase
{
    #[Test]
    public function it_lists_the_rules_in_the_order_the_site_tries_them(): void
    {
        SeoUrl::query()->create(['match_type' => 'regex', 'pattern' => '#^/a#']);
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/a']);
        SeoUrl::query()->create(['match_type' => 'mask', 'pattern' => '/a/*']);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api('urls'));

        $response->assertOk();
        $this->assertSame(['exact', 'mask', 'regex'], array_column($response->json('data'), 'match_type'));
    }

    #[Test]
    public function it_writes_a_rule_with_every_language_of_every_field(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('urls'), [
            'match_type' => 'exact',
            'pattern' => '/about/',
            'title' => ['ru' => 'О нас', 'uk' => 'Про нас', 'xx' => 'Nowhere'],
            'robots' => 'noindex, nofollow',
        ]);

        $response->assertCreated();

        $rule = SeoUrl::query()->firstOrFail();

        // Normalised on the way in, so a rule written with a trailing slash matches an address
        // arriving without one.
        $this->assertSame('/about', $rule->pattern);
        // A language the site does not publish in is not kept: it would sit in the column for
        // ever, invisible to the panel that can only show the ones it knows.
        $this->assertSame(['ru' => 'О нас', 'uk' => 'Про нас'], $rule->getTranslations('title'));
    }

    #[Test]
    public function a_regular_expression_that_will_not_compile_is_refused_under_its_field(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('urls'), ['match_type' => 'regex', 'pattern' => '#^/catalog/(#'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pattern');
    }

    #[Test]
    public function reading_is_not_writing(): void
    {
        $reader = $this->editor(['seo.view']);

        $this->actingAs($reader, 'cms')->getJson($this->api('urls'))->assertOk();
        $this->actingAs($reader, 'cms')
            ->postJson($this->api('urls'), ['match_type' => 'exact', 'pattern' => '/about'])
            ->assertForbidden();
    }

    #[Test]
    public function a_stranger_is_not_let_in(): void
    {
        $this->getJson($this->api('urls'))->assertUnauthorized();
    }

    #[Test]
    public function test_url_says_which_rule_matched_and_what_the_page_ends_up_with(): void
    {
        SeoUrl::query()->create(['match_type' => 'mask', 'pattern' => '/catalog/*', 'title' => ['ru' => 'Каталог']]);
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/catalog/shoes']);

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('test-url'), ['url' => '/catalog/shoes/', 'locale' => 'ru']);

        $response->assertOk();
        $this->assertSame('/catalog/shoes', $response->json('data.url'));
        $this->assertSame('/catalog/*', $response->json('data.matched.pattern'));
        $this->assertSame('Каталог', $response->json('data.seo.title'));
        // Only the sources that had something to say: with no settings written, the defaults
        // answer null and stay out of the chain rather than filling it with empty rows.
        $this->assertSame(['UrlRuleSource'], array_column($response->json('data.chain'), 'source'));
        $this->assertNull($response->json('data.redirect'));
    }

    #[Test]
    public function test_url_says_when_an_address_is_redirected_before_any_of_that(): void
    {
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/new']);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('test-url'), ['url' => '/old'])
            ->assertOk()
            ->assertJsonPath('data.redirect.target', '/new');
    }

    #[Test]
    public function it_writes_a_redirect_and_marks_one_that_points_at_itself(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('redirects'), [
            'match_type' => 'exact',
            'pattern' => '/old/',
            'target' => '/old',
            'status' => 302,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 302)
            ->assertJsonPath('data.is_loop', true);
    }

    #[Test]
    public function the_settings_screen_grew_a_seo_tab(): void
    {
        $response = $this->actingAs($this->editor(['settings.view', 'seo.view']), 'cms')
            ->getJson('/api/cms/screens/settings.index');

        $response->assertOk();

        $ids = $this->ids($response->json('data.root') ?? []);

        $this->assertContains('seo', $ids);
        // The ids are a contract: a project writes its own patch against them.
        $this->assertContains('title-template', $ids);
        $this->assertContains('org-socials', $ids);
    }

    private function api(string $path): string
    {
        return '/api/cms/seo/'.$path;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<string>
     */
    private function ids(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            $ids[] = (string) ($node['id'] ?? '');
            $ids = [...$ids, ...$this->ids(is_array($node['children'] ?? null) ? $node['children'] : [])];
        }

        return $ids;
    }
}
