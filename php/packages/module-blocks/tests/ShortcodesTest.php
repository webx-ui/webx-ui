<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcode;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Probes\ProbeSet;
use WebxUi\Audit\Probes\SiteClient;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Blocks\Audit\HardcodedValuesCheck;
use WebxUi\Blocks\Audit\UnknownShortcodesCheck;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Shortcodes in block content: resolved once, in the values a template reads, so that the page,
 * the preview and `blocks_render` agree — and audited where they are mistyped or missing.
 */
final class ShortcodesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);

        $shortcodes = $this->app->make(Shortcodes::class);
        $shortcodes->register('dot', '<span class="accent-dot">.</span>', plain: '.');
        $shortcodes->source(static fn (): array => [
            new Shortcode('phone', '<a href="tel:+15550100">555 0100</a>', '555 0100', origin: 'settings'),
            new Shortcode('email', '<a href="mailto:hi@example.com">hi@example.com</a>', 'hi@example.com', origin: 'settings'),
        ]);

        $this->publish('hero', '<h1>{{ $heading }}</h1><p>{{ $lead }}</p><div>{!! $body !!}</div><a href="tel:{{ $tel }}" title="@shortcodesPlain($heading)">x</a>', [], ['schema' => [
            ['id' => 'heading', 'type' => 'wx-input', 'localized' => true],
            ['id' => 'lead', 'type' => 'wx-textarea'],
            ['id' => 'body', 'type' => 'wx-rich-text'],
            ['id' => 'tel', 'type' => 'wx-input', 'props' => ['type' => 'tel']],
        ]]);

        $this->publish('contacts', '@foreach ($items ?? [] as $item)<li>{{ $item[\'label\'] }}</li>@endforeach', [], ['schema' => [
            ['id' => 'items', 'type' => 'wx-repeater', 'children' => [
                ['id' => 'items-label', 'type' => 'wx-input', 'name' => 'label'],
            ]],
        ]]);
    }

    #[Test]
    public function a_text_field_prints_its_shortcodes_as_html_and_the_text_around_them_escaped(): void
    {
        $html = $this->render([$this->node('hero', [
            'heading' => ['en' => 'Deeply heard[dot] <i>Fish & chips</i>'],
            'lead' => 'Call [phone], or write [[email]]',
            'body' => '<p>Mail [email]</p><p><code>[phone]</code></p>',
            'tel' => '+1 555 0100',
        ])]);

        $this->assertSame(
            '<h1>Deeply heard<span class="accent-dot">.</span> &lt;i&gt;Fish &amp; chips&lt;/i&gt;</h1>'
            .'<p>Call <a href="tel:+15550100">555 0100</a>, or write [email]</p>'
            .'<div><p>Mail <a href="mailto:hi@example.com">hi@example.com</a></p><p><code>[phone]</code></p></div>'
            .'<a href="tel:+1 555 0100" title="Deeply heard. &lt;i&gt;Fish &amp; chips&lt;/i&gt;">x</a>',
            $html,
        );
    }

    /* A heading with nothing to replace is the string it was, markup and all, as before. */
    #[Test]
    public function a_text_without_a_shortcode_reaches_the_template_unchanged(): void
    {
        $this->assertSame(
            '<h1>Plain [1] &amp; more</h1><p></p><div></div><a href="tel:" title="Plain [1] &amp; more">x</a>',
            $this->render([$this->node('hero', ['heading' => ['en' => 'Plain [1] & more']])]),
        );
    }

    #[Test]
    public function the_fields_of_a_repeater_item_are_resolved_too(): void
    {
        $this->assertSame(
            '<li>Phone: <a href="tel:+15550100">555 0100</a></li><li>Full stop[dot] here</li>',
            str_replace('<span class="accent-dot">.</span>', '[dot]', $this->render([$this->node('contacts', ['items' => [['label' => 'Phone: [phone]'], ['label' => 'Full stop[dot] here']]])])),
        );
    }

    #[Test]
    public function the_outline_an_agent_reads_names_a_block_in_plain_text(): void
    {
        $page = Page::query()->create(['title' => 'Home', 'slug' => 'home', 'blocks' => [
            $this->node('hero', ['heading' => ['en' => 'Deeply heard[dot]']], 'k-hero'),
        ]]);

        WebxServer::actingAs($this->editor(), 'cms')
            ->tool(new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_get_content')), ['entity' => 'note', 'id' => $page->id, 'outline' => true])
            ->assertOk()
            ->assertStructuredContent(static function (AssertableJson $json): void {
                self::assertSame(['k-hero' => 'Deeply heard.'], array_column($json->etc()->toArray()['outline'], 'label', 'key'));
            });
    }

    #[Test]
    public function agents_read_the_shortcodes_and_both_their_renderings(): void
    {
        $resource = array_values(array_filter(
            $this->app->make(ToolRegistry::class)->resources(),
            static fn (McpResource $resource): bool => $resource->uri === 'blocks://shortcodes',
        ))[0];

        $read = ($resource->handler)([]);

        $this->assertSame(['dot', 'email', 'phone'], array_column($read['shortcodes'], 'name'));
        $this->assertSame(['.', '<span class="accent-dot">.</span>'], [$read['shortcodes'][0]['plain'], $read['shortcodes'][0]['html']]);
        $this->assertNotEmpty($read['rules']);
    }

    #[Test]
    public function the_audit_reports_a_mistyped_shortcode_and_leaves_prose_in_brackets_alone(): void
    {
        $page = Page::query()->create(['title' => 'About', 'slug' => 'about', 'blocks' => [
            $this->node('hero', [
                'heading' => ['en' => 'Hello [phnoe] and [dto]'],
                'lead' => 'See [1], [sic], [ok], [[phnoe]] and [promo code=x]',
                'body' => '<p>[phone] is fine</p>',
            ], 'k-hero'),
        ]]);

        $findings = $this->audit(UnknownShortcodesCheck::class);

        $this->assertCount(1, $findings);
        $this->assertSame($page->getMorphClass().':'.$page->id, $findings[0]->key);
        $this->assertSame(
            ['[phnoe] → [phone]', '[dto] → [dot]', '[promo code=x]'],
            array_column($findings[0]->details['table']['rows'], 'value'),
        );
        $this->assertSame(['heading (en)', 'heading (en)', 'lead'], array_column($findings[0]->details['table']['rows'], 'field'));
    }

    #[Test]
    public function the_audit_reports_a_value_typed_where_a_data_shortcode_holds_it(): void
    {
        Page::query()->create(['title' => 'Contacts', 'slug' => 'contacts', 'blocks' => [
            $this->node('hero', [
                'heading' => ['en' => 'Call (555) 01-00 now'],
                'lead' => 'Write to HI@example.com',
                // The number in a tel input is on purpose; a longer number around the digits is
                // another number.
                'tel' => '+1 555 0100',
                'body' => '<p>Order 1555010099 and [phone]</p>',
            ], 'k-hero'),
            $this->node('contacts', ['items' => [['label' => 'Phone 555-0100']]], 'k-list'),
        ]]);

        $findings = $this->audit(HardcodedValuesCheck::class);

        $this->assertCount(1, $findings);
        $this->assertSame(
            ['555 0100 → [phone]', 'hi@example.com → [email]', '555 0100 → [phone]'],
            array_column($findings[0]->details['table']['rows'], 'value'),
        );
        $this->assertSame(['heading (en)', 'lead', 'items.1.label'], array_column($findings[0]->details['table']['rows'], 'field'));
        $this->assertSame([true, true, true], array_column($findings[0]->details['table']['rows'], 'published'));
    }

    /**
     * @param  class-string<UnknownShortcodesCheck|HardcodedValuesCheck>  $check
     * @return list<Finding>
     */
    private function audit(string $check): array
    {
        $context = new AuditContext(new AuditRun, new HostClassifier(['example.test']), $this->app->make(SiteClient::class), new ProbeSet, $this->app->make('config'));

        return array_values(iterator_to_array($this->app->make($check)->run($context), false));
    }
}
