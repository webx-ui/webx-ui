<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Tool;
use WebxUi\Seo\Faq\PageFaq;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\SeoModule;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Rendering\Seo;

/**
 * The page FAQ is off by default, and off means gone (§18.1, decision 2): no routes, no tools,
 * no questions in the rule's form or the page's markup, a component that prints nothing — and
 * the questions still there when it is turned on again.
 */
final class FaqFlagTest extends TestCase
{
    #[Test]
    public function a_feature_that_is_off_has_no_routes_and_no_tools(): void
    {
        $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/seo/faq/export')->assertNotFound();
        $status = $this->actingAs($this->editor(), 'cms')->postJson('/api/cms/seo/faq/import')->getStatusCode();
        $this->assertContains($status, [404, 405]);

        $names = array_map(static fn (Tool $tool): string => $tool->name, app(SeoModule::class)->mcpTools());
        $this->assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'faq_'))));
    }

    #[Test]
    public function the_rule_form_neither_shows_nor_touches_the_questions_while_it_is_off(): void
    {
        $rule = $this->ruleWithFaq();

        $data = $this->actingAs($this->editor(), 'cms')->putJson("/api/cms/seo/urls/{$rule->id}", [
            'match_type' => 'exact',
            'pattern' => '/delivery',
            'faq' => [],
        ])->assertOk()->json('data');

        $this->assertArrayNotHasKey('faq', $data);
        $this->assertArrayNotHasKey('faq_count', $data);
        $this->assertSame(1, $rule->faqItems()->count());
    }

    #[Test]
    public function the_questions_wait_silently_until_the_feature_is_turned_on(): void
    {
        $this->assertTrue(Schema::hasTable('seo_faq_items'));

        $this->ruleWithFaq();
        app()->setLocale('ru');
        $this->app->instance('request', Request::create('/delivery'));

        $this->assertSame('', trim(Blade::render('<x-webx-seo::faq />')));
        $this->assertStringNotContainsString('FAQPage', (string) app(Seo::class)->head(null, '/delivery', 'ru'));

        config()->set('webx-seo.faq.enabled', true);

        $this->assertStringContainsString('Сколько ждать?', Blade::render('<x-webx-seo::faq />'));
        $this->assertStringContainsString('FAQPage', (string) app(Seo::class)->head(null, '/delivery', 'ru'));
    }

    private function ruleWithFaq(): SeoUrl
    {
        $rule = SeoUrl::query()->create(['match_type' => UrlMatcher::EXACT, 'pattern' => '/delivery']);

        app(PageFaq::class)->write($rule, [['question' => ['ru' => 'Сколько ждать?'], 'answer' => ['ru' => 'Два дня.']]]);

        return $rule;
    }
}
