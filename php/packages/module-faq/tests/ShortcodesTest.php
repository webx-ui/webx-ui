<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Faq\Models\Question;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;

/**
 * An answer takes shortcodes: the block prints it resolved, the `FAQPage` carries its plain text,
 * and what an agent writes keeps the bracket — the record holds what was typed, never the result.
 */
final class ShortcodesTest extends TestCase
{
    private const LINK = '<a href="tel:+80012345678">+800 1234 5678</a>';

    protected function setUp(): void
    {
        parent::setUp();

        app(Shortcodes::class)->register('phone', self::LINK, plain: '+800 1234 5678');
    }

    #[Test]
    public function the_block_prints_the_answer_resolved_and_the_markup_carries_its_plain_text(): void
    {
        $this->installBlock();
        $this->question('Refunds', answer: ['en' => '<p>Call [phone] today.</p>']);

        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);
        $page = new Page(['title' => 'FAQ', 'slug' => 'faq']);
        $page->appendTo($home);
        $page->blocks = [['key' => 'k1', 'type' => 'faq', 'values' => []]];
        $page->save();
        $page->publish();

        $body = (string) $this->get('/faq')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="b-faq__answer"><p>Call '.self::LINK.' today.</p></div>', $body);
        $this->assertStringContainsString('"text":"Call +800 1234 5678 today."', $body);
        $this->assertStringNotContainsString('[phone]', $body);
    }

    #[Test]
    public function an_answer_an_agent_writes_keeps_the_bracket(): void
    {
        $tool = new RegistryTool($this->app->make(ToolRegistry::class)->tool('faq_create'));

        WebxServer::actingAs($this->editor(), 'cms')
            ->tool($tool, ['question' => 'How do I pay?', 'answer' => '<p>Call [phone] today.</p>'])
            ->assertHasNoErrors();

        $this->assertSame('<p>Call [phone] today.</p>', Question::query()->firstOrFail()->getTranslation('answer', 'en'));
    }
}
