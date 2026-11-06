<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Seo\Faq\PageFaq;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Rendering\Seo;

/**
 * A page FAQ's answer takes shortcodes, as `module-faq`'s does: the component prints it resolved,
 * the `FAQPage` carries its plain text, and the rule keeps the bracket.
 */
final class FaqShortcodesTest extends TestCase
{
    private const LINK = '<a href="tel:+80012345678">+800 1234 5678</a>';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-seo.faq.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(Shortcodes::class)->register('phone', self::LINK, plain: '+800 1234 5678');

        $rule = SeoUrl::query()->create(['match_type' => UrlMatcher::EXACT, 'pattern' => '/delivery']);
        app(PageFaq::class)->write($rule, [['question' => ['ru' => 'How long?'], 'answer' => ['ru' => '<p>Call [phone] today.</p>']]]);
    }

    #[Test]
    public function the_faq_prints_the_answer_resolved(): void
    {
        app()->setLocale('ru');
        $this->app->instance('request', Request::create('/delivery'));

        $html = Blade::render('<x-webx-seo::faq />');

        $this->assertStringContainsString('<div class="webx-faq__answer"><p>Call '.self::LINK.' today.</p></div>', $html);
        $this->assertStringNotContainsString('[phone]', $html);
        $this->assertSame('<p>Call [phone] today.</p>', SeoUrl::query()->firstOrFail()->faqItems->first()?->getTranslation('answer', 'ru'));
    }

    /* The section is on the page, under the answer; the answer of `FAQPage` is its words. */
    #[Test]
    public function a_block_shortcode_in_an_answer_stays_out_of_the_faq_page(): void
    {
        app(Shortcodes::class)->register('section', '<section><h2>Picks</h2>Trail camera 10.00 Buy</section>', block: true);

        $rule = SeoUrl::query()->create(['match_type' => UrlMatcher::EXACT, 'pattern' => '/returns']);
        app(PageFaq::class)->write($rule, [
            ['question' => ['ru' => 'What to take?'], 'answer' => ['ru' => '<p>Call [phone] today.</p><p>[section]</p>']],
            ['question' => ['ru' => 'Only cards?'], 'answer' => ['ru' => '<p>[section]</p>']],
        ]);

        $questions = app(PageFaq::class)->questions($rule->refresh(), 'ru');

        $this->assertCount(1, $questions, 'a section alone is no answer');
        $this->assertStringContainsString('Trail camera', $questions[0]['answer']);

        $head = (string) app(Seo::class)->head(null, '/returns', 'ru');

        $this->assertStringContainsString('"text":"Call +800 1234 5678 today."', $head);
        $this->assertStringNotContainsString('Trail camera', $head);
    }

    #[Test]
    public function the_faq_page_carries_the_plain_text(): void
    {
        $head = (string) app(Seo::class)->head(null, '/delivery', 'ru');

        $this->assertStringContainsString('"text":"Call +800 1234 5678 today."', $head);
        $this->assertStringNotContainsString('[phone]', $head);
        $this->assertStringNotContainsString('tel:', $head);
    }
}
