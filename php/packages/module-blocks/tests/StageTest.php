<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\DynamicComponent;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

final class StageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::forgetDynamicComponents();

        // Per test and under a prefix of its own: `<x-dynamic-component>` remembers what it has
        // resolved in static state shared by the whole run.
        Blade::anonymousComponentPath(__DIR__.'/Fixtures/views', 'stage-site');
    }

    protected function tearDown(): void
    {
        self::forgetDynamicComponents();

        parent::tearDown();
    }

    /**
     * `<x-dynamic-component>` builds its tag compiler once per process, around the Blade of the
     * application that asked first. Left behind by this test, it is the compiler every package
     * after this one resolves its layout with — one that has never heard of their `site::` —
     * and their tests fail with "Unable to locate a class or view" in files nobody touched.
     */
    private static function forgetDynamicComponents(): void
    {
        // Null is where the property starts before the first compile, whatever its docblock says.
        (new ReflectionProperty(DynamicComponent::class, 'compiler'))->setValue(null, null);
        (new ReflectionProperty(DynamicComponent::class, 'componentClasses'))->setValue(null, []);
    }

    #[Test]
    public function the_stage_is_for_editors_only(): void
    {
        $this->get('/_preview/block-stage')->assertStatus(401);

        $this->actingAs($this->editor([]), 'cms')->get('/_preview/block-stage')->assertForbidden();
        $this->actingAs($this->editor(['blocks.view']), 'cms')->get('/_preview/block-stage')->assertOk();
    }

    #[Test]
    public function without_a_layout_the_stage_is_a_bare_document_with_an_empty_place(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->get('/_preview/block-stage')->assertOk();

        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertSee('<!doctype html>', false);
        $response->assertSee('<!--wx:sample--><!--/wx:sample-->', false);
        $response->assertSee('<style id="wx-stage-styles"></style>', false);
        $response->assertSee('runtime.js', false);
    }

    #[Test]
    public function with_a_layout_the_block_stands_between_the_header_and_the_footer_of_the_site(): void
    {
        config()->set('webx-blocks.layout', 'stage-site::layout');

        $html = (string) $this->actingAs($this->editor(), 'cms')->get('/_preview/block-stage')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="stylesheet" href="/site.css">', $html);

        $header = strpos($html, 'Header of the site');
        $place = strpos($html, '<!--wx:sample--><!--/wx:sample-->');
        $footer = strpos($html, 'Footer of the site');

        $this->assertIsInt($header);
        $this->assertIsInt($place);
        $this->assertIsInt($footer);
        $this->assertTrue($header < $place && $place < $footer);

        // The head slot reaches the layout's head, so the block's styles land where the site's do.
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'wx-stage-styles'));
    }

    #[Test]
    public function a_render_names_the_stage_it_is_drawn_on(): void
    {
        $block = $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>');

        $response = $this->actingAs($this->editor(['blocks.view']), 'cms')
            ->postJson('/'.trim((string) config('webx-admin.api_path'), '/')."/blocks/{$block->id}/render", ['values' => ['title' => 'x']])
            ->assertOk();

        $this->assertSame('/_preview/block-stage', $response->json('data.stage'));
        $this->assertStringStartsWith('<!--wx:sample-->', (string) $response->json('data.html'));
    }
}
