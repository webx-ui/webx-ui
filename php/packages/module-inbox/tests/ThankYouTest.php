<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Submission;

/**
 * The visitor's answers in the thank-you — `{{field.<name>}}` in `thank-you.heading` and
 * `thank-you.text` — and `<x-webx-inbox::form again>`, the way back to the fields after it.
 */
final class ThankYouTest extends TestCase
{
    private const array THANKS = [
        'thank-you.heading' => ['en' => 'Thanks, {{field.name}}.'],
        'thank-you.text' => ['en' => '<p>We will get back to you at {{ field.email }}.</p>'],
    ];

    private const string NAME = '<em class="webx-form__answer-value">Ada</em>';

    private const string EMAIL = '<em class="webx-form__answer-value">ada@example.test</em>';

    #[Test]
    public function the_script_is_answered_with_the_answers_in_the_thank_you(): void
    {
        $this->form('contact', [], self::THANKS);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test']])
            ->assertOk()
            ->assertExactJson([
                'ok' => true,
                // The text of the heading for whoever reads only that; its HTML beside it.
                'heading' => 'Thanks, Ada.',
                'heading_html' => 'Thanks, '.self::NAME.'.',
                'message' => '<p>We will get back to you at '.self::EMAIL.'.</p>',
                'redirect' => null,
                'html' => null,
            ]);
    }

    #[Test]
    public function the_sites_shortcodes_in_the_thank_you_are_printed_and_the_visitors_brackets_are_not(): void
    {
        app(Shortcodes::class)->register('phone', '<a href="tel:+80012345678">+800 1234 5678</a>', plain: '+800 1234 5678');
        $this->form('contact', [], ['thank-you.text' => ['en' => '<p>Call [phone], {{field.name}}.</p>']]);

        // What the administrator wrote is the number; what the visitor typed stays as typed.
        $this->postJson($this->intake(), ['fields' => ['name' => '[phone]', 'email' => 'ada@example.test']])
            ->assertOk()
            ->assertJsonPath('message', '<p>Call <a href="tel:+80012345678">+800 1234 5678</a>, <em class="webx-form__answer-value">[phone]</em>.</p>');
    }

    #[Test]
    public function a_submission_without_javascript_comes_back_with_the_answers_in_the_thank_you(): void
    {
        $this->form('contact', [], self::THANKS);

        $this->post($this->intake(), [
            '_webx_form' => 'webx-inbox-contact',
            'webx_form' => 'contact',
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ], ['referer' => 'http://localhost/page'])
            ->assertRedirect('http://localhost/page')
            ->assertSessionHas('webx-form', [
                'form' => 'webx-inbox-contact',
                'heading' => 'Thanks, Ada.',
                'heading_html' => 'Thanks, '.self::NAME.'.',
                'message' => '<p>We will get back to you at '.self::EMAIL.'.</p>',
                'html' => null,
            ]);

        $html = $this->render();

        $this->assertStringContainsString('<p class="webx-form__heading">Thanks, '.self::NAME.'.</p>', $html);
        $this->assertStringContainsString('<p>We will get back to you at '.self::EMAIL.'.</p>', $html);
    }

    #[Test]
    public function a_view_of_before_the_engine_is_given_the_heading_as_text(): void
    {
        $this->form('contact', [], self::THANKS);

        // No `_webx_form`: the view a site published before W6.4 reads `webx-inbox` and escapes it.
        $this->post($this->intake(), ['webx_form' => 'contact', 'fields' => ['name' => 'Ada', 'email' => 'ada@example.test']])
            ->assertSessionHas('webx-inbox.heading', 'Thanks, Ada.')
            ->assertSessionHas('webx-inbox.message', '<p>We will get back to you at '.self::EMAIL.'.</p>');
    }

    #[Test]
    public function what_a_visitor_wrote_is_escaped(): void
    {
        $this->form('contact', [], self::THANKS);

        $answer = $this->postJson($this->intake(), ['fields' => ['name' => '<script>alert(1)</script> & Co', 'email' => 'ada@example.test']])->assertOk();

        $value = '<em class="webx-form__answer-value">&lt;script&gt;alert(1)&lt;/script&gt; &amp; Co</em>';

        $this->assertSame('Thanks, '.$value.'.', $answer->json('heading_html'));
        $this->assertSame('Thanks, <script>alert(1)</script> & Co.', $answer->json('heading'));
        $this->assertStringNotContainsString('<script>', (string) $answer->json('message'));
    }

    #[Test]
    public function the_heading_around_the_values_is_escaped_too(): void
    {
        // The heading is text in the panel: an ampersand in it is an ampersand, not markup.
        $this->form('contact', [], ['thank-you.heading' => ['en' => 'Q&A <b>team</b> thanks {{field.name}}']]);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test']])
            ->assertJson(['heading_html' => 'Q&amp;A &lt;b&gt;team&lt;/b&gt; thanks '.self::NAME, 'heading' => 'Q&A <b>team</b> thanks Ada']);
    }

    #[Test]
    public function an_empty_answer_or_an_unknown_name_is_nothing(): void
    {
        $this->form('contact', [], [
            'thank-you.heading' => ['en' => 'Thanks{{field.message}}{{field.nobody}}!'],
            'thank-you.text' => ['en' => '<p>[{{field.message}}]</p>'],
        ]);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => '  ']])
            ->assertJson(['heading' => 'Thanks!', 'heading_html' => 'Thanks!', 'message' => '<p>[]</p>']);
    }

    #[Test]
    public function a_heading_with_nothing_to_fill_in_stays_text(): void
    {
        $this->form('contact', [], ['thank-you.heading' => ['en' => 'Thank you & goodbye']]);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test']])
            ->assertJson(['heading' => 'Thank you & goodbye', 'heading_html' => null, 'message' => null]);
    }

    #[Test]
    public function a_file_is_not_put_in(): void
    {
        Storage::fake('inbox');
        $this->form('apply', [
            ['name' => 'name', 'type' => FieldType::Text],
            ['name' => 'cv', 'type' => FieldType::File],
        ], ['thank-you.text' => ['en' => '<p>{{field.name}}: {{field.cv}}</p>']]);

        $this->postJson($this->intake('apply'), [
            'fields' => ['name' => 'Ada', 'cv' => UploadedFile::fake()->create('resume.pdf', 20, 'application/pdf')],
        ])->assertOk()->assertJson(['message' => '<p>'.self::NAME.': </p>']);

        $this->assertSame('resume.pdf', Submission::query()->sole()->value('cv')?->value);
    }

    #[Test]
    public function a_choice_is_put_in_by_its_label(): void
    {
        $this->form('contact', [
            ['name' => 'plan', 'type' => FieldType::Select, 'options' => ['choices' => [['value' => 'b', 'label' => 'Plan B']]]],
        ], ['thank-you.text' => ['en' => '<p>{{field.plan}}</p>']]);

        $this->postJson($this->intake(), ['fields' => ['plan' => 'b']])
            ->assertOk()->assertJson(['message' => '<p><em class="webx-form__answer-value">Plan B</em></p>']);
    }

    #[Test]
    public function a_robot_is_thanked_by_its_name_like_anybody_else(): void
    {
        $this->form('contact', [], self::THANKS);

        // Dropped by the honeypot, and told nothing of it: the same thank-you a person gets.
        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test'], 'webx_hp' => 'https://spam.test'])
            ->assertOk()
            ->assertJson(['heading_html' => 'Thanks, '.self::NAME.'.']);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function the_way_back_to_the_fields_is_off_unless_asked_for(): void
    {
        $this->form();

        $this->assertStringNotContainsString('webx-form__again', $this->render());

        $html = $this->render('<x-webx-inbox::form slug="contact" again />');

        $this->assertMatchesRegularExpression('~<a class="webx-form__again" href="http://localhost/page" data-webx-again hidden>Send another</a>~', $html);
    }

    #[Test]
    public function without_javascript_the_thank_you_takes_the_place_of_the_fields_and_links_back(): void
    {
        $this->form('contact', [], self::THANKS);

        $this->post($this->intake(), [
            '_webx_form' => 'webx-inbox-contact',
            'webx_form' => 'contact',
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ], ['referer' => 'http://localhost/page'])->assertRedirect('http://localhost/page');

        $html = $this->render('<x-webx-inbox::form slug="contact" again />');

        $this->assertStringContainsString('<div class="webx-form__body" hidden>', $html);
        $this->assertStringContainsString('<a class="webx-form__again" href="http://localhost/page" data-webx-again>Send another</a>', $html);

        // The link is the page again, and the page without the flash is the form again.
        $fresh = $this->render('<x-webx-inbox::form slug="contact" again />');
        $this->assertStringContainsString('<div class="webx-form__body">', $fresh);
        $this->assertStringNotContainsString('webx-form__heading', $fresh);
    }

    private function render(string $blade = '<x-webx-inbox::form slug="contact" />'): string
    {
        $this->app->forgetScopedInstances();

        Route::middleware('web')->get('/page', static fn (): string => Blade::render($blade));

        return $this->get('/page')->assertOk()->getContent() ?: '';
    }
}
