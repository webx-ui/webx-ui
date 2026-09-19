<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Types\RichTextType;

final class RichTextTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-localization.locales', [
            ['code' => 'ru', 'default' => true],
            ['code' => 'uk'],
        ]);

        Screens::register('article.form', [
            [
                'id' => 'card',
                'type' => 'wx-card',
                'children' => [
                    ['id' => 'body', 'type' => 'wx-rich-text', 'name' => 'body', 'label' => 'Body'],
                    ['id' => 'text', 'type' => 'wx-rich-text', 'name' => 'text', 'label' => 'Text', 'localized' => true],
                    ['id' => 'lead', 'type' => 'wx-rich-text', 'name' => 'lead', 'label' => 'Lead', 'props' => ['maxlength' => 40]],
                ],
            ],
        ]);
    }

    private function values(): ScreenValues
    {
        return $this->app->make(ScreenValues::class);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function save(array $input): array
    {
        return $this->values()->validate('article.form', $input);
    }

    #[Test]
    public function a_document_is_kept_as_it_was_written(): void
    {
        $html = '<h2>Release</h2><p>A <strong>word</strong> and a <a href="/pages/two">link</a>.</p>';

        $this->assertSame(['body' => $html], $this->save(['body' => $html]));
    }

    #[Test]
    public function what_the_allowlist_does_not_name_is_taken_out(): void
    {
        $stored = $this->save([
            'body' => '<p onclick="steal()">Text</p><script>steal()</script><p><a href="javascript:steal()">go</a></p>',
        ]);

        $this->assertSame('<p>Text</p><p><a>go</a></p>', $stored['body']);
    }

    #[Test]
    public function an_unknown_wrapper_loses_the_tag_and_keeps_the_text(): void
    {
        $stored = $this->save(['body' => '<section class="x"><p>Kept</p></section>']);

        $this->assertSame('<p>Kept</p>', $stored['body']);
    }

    #[Test]
    public function the_youtube_embed_the_editor_writes_survives_and_any_other_frame_does_not(): void
    {
        $stored = $this->save([
            'body' => '<div data-youtube-video><iframe src="https://www.youtube-nocookie.com/embed/x"></iframe></div>'
                .'<iframe src="https://example.com/anything"></iframe>',
        ]);

        $this->assertSame(
            '<div data-youtube-video><iframe src="https://www.youtube-nocookie.com/embed/x"></iframe></div>',
            $stored['body'],
        );
    }

    #[Test]
    public function a_link_that_opens_elsewhere_is_given_a_rel(): void
    {
        $stored = $this->save(['body' => '<p><a href="https://example.com" target="_blank">out</a></p>']);

        $this->assertStringContainsString('rel="noopener noreferrer"', (string) $stored['body']);
    }

    #[Test]
    public function an_emptied_editor_stores_nothing_at_all(): void
    {
        $this->assertSame(['body' => null], $this->save(['body' => '<p></p>']));
        $this->assertSame(['body' => null], $this->save(['body' => '   ']));
        $this->assertSame(['body' => null], $this->save(['body' => null]));

        // A picture with no words around it is still a document.
        $stored = $this->save(['body' => '<p><img src="/files/one.png" alt="One"></p>']);
        $this->assertNotNull($stored['body']);
    }

    #[Test]
    public function every_language_is_checked_and_cleaned_on_its_own(): void
    {
        $stored = $this->save([
            'text' => [
                'ru' => '<p>Привет<script>steal()</script></p>',
                'uk' => '<p></p>',
                'de' => '<p>Not a language of this site</p>',
            ],
        ]);

        $this->assertSame(['ru' => '<p>Привет</p>', 'uk' => null], $stored['text']);
    }

    #[Test]
    public function a_node_that_asks_for_a_short_lead_gets_one(): void
    {
        $this->expectException(ValidationException::class);

        $this->save(['lead' => '<p>'.str_repeat('word ', 20).'</p>']);
    }

    #[Test]
    public function the_default_limit_is_a_page_of_an_article_rather_than_a_title(): void
    {
        $this->assertSame(262144, RichTextType::MAX);
    }

    #[Test]
    public function the_library_key_of_a_picture_survives_the_allowlist(): void
    {
        $stored = $this->save([
            'body' => '<p><img src="https://cdn.example.com/m/hero.png?v=abc" data-wx-path="2026/09/hero.png" alt="Hero"></p>',
        ]);

        $this->assertStringContainsString('data-wx-path="2026/09/hero.png"', (string) $stored['body']);
        // The address is kept as a cache, not as the record: the panel edits what is stored.
        $this->assertStringContainsString('src="https://cdn.example.com/m/hero.png?v=abc"', (string) $stored['body']);
    }

    #[Test]
    public function the_site_reads_the_address_the_library_gives_now(): void
    {
        $this->app->bind(AssetUrls::class, fn (): AssetUrls => new class implements AssetUrls
        {
            /**
             * @param  list<string>  $paths
             * @return array<string, string|null>
             */
            public function urls(array $paths): array
            {
                return ['2026/09/hero.png' => '/storage/media/2026/09/hero.png?v=new'];
            }
        });

        $node = ['type' => 'wx-rich-text', 'name' => 'body'];
        $stored = '<p><img src="https://cdn.example.com/m/hero.png?v=old" data-wx-path="2026/09/hero.png">'
            .'<img src="https://elsewhere.example/logo.png">'
            .'<img src="/m/gone.png" data-wx-path="2020/01/gone.png"></p>';

        $read = (string) $this->values()->resolve($node, $stored);

        $this->assertStringContainsString('src="/storage/media/2026/09/hero.png?v=new"', $read);
        $this->assertStringContainsString('src="https://elsewhere.example/logo.png"', $read, 'a picture with no key is not ours to move');
        $this->assertStringContainsString('src="/m/gone.png"', $read, 'a key the library lost keeps the address it had');
    }

    #[Test]
    public function a_site_with_no_file_manager_reads_the_document_as_it_was_written(): void
    {
        $node = ['type' => 'wx-rich-text', 'name' => 'body'];
        $stored = '<p><img src="/m/hero.png" data-wx-path="2026/09/hero.png"></p>';

        $this->assertSame($stored, $this->values()->resolve($node, $stored));
    }
}
