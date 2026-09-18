<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Submission;

/**
 * `<x-webx-form>` — the form of the panel, printed on the site (§10).
 *
 * Everything here is about the half of the module a visitor meets, and most of it is about the
 * half that works with JavaScript switched off: the markup, what the fields become, and what
 * comes back through the session after a plain POST.
 */
final class PublicFormTest extends TestCase
{
    #[Test]
    public function it_prints_a_form_that_posts_to_the_intake(): void
    {
        $this->form();

        $html = $this->render();

        $this->assertStringContainsString('action="'.url($this->intake()).'"', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('class="wx-form"', $html);

        // The marker that tells a page with two forms whose errors came back.
        $this->assertStringContainsString('name="webx_form" value="contact"', $html);

        // The names the intake validates under, so an error lands beneath the input that
        // caused it with nothing in between to translate them.
        $this->assertStringContainsString('name="fields[name]"', $html);
        $this->assertStringContainsString('name="fields[email]"', $html);
        $this->assertStringContainsString('name="fields[message]"', $html);
    }

    #[Test]
    public function it_prints_nothing_for_a_form_that_is_not_there_or_is_switched_off(): void
    {
        $this->form('contact')->update(['is_enabled' => false]);

        $this->assertSame('', trim($this->render()));
        $this->assertSame('', trim($this->render('<x-webx-form slug="nothing-like-it" />')));
    }

    #[Test]
    public function it_draws_a_control_for_every_type(): void
    {
        $this->form('everything', [
            ['name' => 'line', 'type' => FieldType::Text, 'options' => ['maxlength' => 40]],
            ['name' => 'mail', 'type' => FieldType::Email],
            ['name' => 'phone', 'type' => FieldType::Tel, 'options' => ['pattern' => '[0-9 +()-]{6,}']],
            ['name' => 'letter', 'type' => FieldType::Textarea, 'options' => ['rows' => 8]],
            ['name' => 'plan', 'type' => FieldType::Select, 'options' => ['choices' => [
                ['value' => 'basic', 'label' => ['en' => 'Basic']],
            ]]],
            ['name' => 'reach', 'type' => FieldType::Radio, 'options' => ['choices' => [
                ['value' => 'call', 'label' => ['en' => 'By phone']],
            ]]],
            ['name' => 'extras', 'type' => FieldType::Checkbox, 'options' => ['choices' => [
                ['value' => 'ssl', 'label' => ['en' => 'SSL']],
            ]]],
            ['name' => 'terms', 'type' => FieldType::Consent, 'is_required' => true, 'options' => [
                'text' => ['en' => 'I agree to the <a href="/privacy">privacy policy</a>'],
            ]],
            ['name' => 'when', 'type' => FieldType::Date, 'options' => ['min' => '2026-01-01']],
            ['name' => 'brief', 'type' => FieldType::File, 'options' => ['extensions' => ['pdf']]],
            ['name' => 'product', 'type' => FieldType::Hidden],
        ]);

        $html = $this->render('<x-webx-form slug="everything" />');

        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('maxlength="40"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('pattern="[0-9 +()-]{6,}"', $html);
        $this->assertStringContainsString('rows="8"', $html);
        $this->assertStringContainsString('<option value="basic"', $html);
        $this->assertStringContainsString('>Basic</option>', $html);
        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('name="fields[extras][]"', $html);
        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('min="2026-01-01"', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('accept=".pdf"', $html);
        $this->assertStringContainsString('type="hidden" id="wx-form-everything-product"', $html);

        // A consent is a sentence with a link in it, not a caption — so it is printed raw and
        // it stands beside its tick rather than above it.
        $this->assertStringContainsString('I agree to the <a href="/privacy">privacy policy</a>', $html);

        // A file field on the form makes the form itself carry files.
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    #[Test]
    public function a_checkbox_group_is_never_required_in_the_markup(): void
    {
        // A browser reads `required` on a checkbox as "this one", not "one of these": on a
        // group it would demand every box. How many are enough is the intake's answer.
        $this->form('survey', [
            ['name' => 'extras', 'type' => FieldType::Checkbox, 'is_required' => true, 'options' => [
                'choices' => [['value' => 'ssl', 'label' => 'SSL'], ['value' => 'cdn', 'label' => 'CDN']],
            ]],
        ]);

        $html = $this->render('<x-webx-form slug="survey" />');

        $this->assertStringContainsString('name="fields[extras][]"', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*type="checkbox"[^>]*\srequired/s', $html);
    }

    #[Test]
    public function it_leaves_out_a_field_that_is_switched_off(): void
    {
        $form = $this->form('contact', [['name' => 'name', 'type' => FieldType::Text]]);
        $this->field($form, ['name' => 'retired', 'type' => FieldType::Text, 'is_enabled' => false]);

        $html = $this->render();

        $this->assertStringContainsString('name="fields[name]"', $html);
        $this->assertStringNotContainsString('fields[retired]', $html);
    }

    #[Test]
    public function a_field_that_is_not_full_width_says_so_in_its_class(): void
    {
        // The panel has a switch for this, so the markup has to carry it somewhere: a site
        // laying the fields out in a grid has nothing else to go on.
        $form = $this->form('contact', [['name' => 'name', 'type' => FieldType::Text, 'is_fullsize' => false]]);
        $this->field($form, ['name' => 'message', 'type' => FieldType::Textarea]);

        $html = $this->render();

        $this->assertMatchesRegularExpression('/wx-form__field[^"]*--half[^"]*"[^>]*data-webx-field="name"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/--half[^"]*"[^>]*data-webx-field="message"/s', $html);
    }

    #[Test]
    public function the_site_fills_the_hidden_fields_by_their_machine_name(): void
    {
        $this->form('contact', [
            ['name' => 'name', 'type' => FieldType::Text],
            ['name' => 'product', 'type' => FieldType::Hidden],
        ]);

        $html = $this->render('<x-webx-form slug="contact" :values="[\'product\' => \'A frame, 2026\']" />');

        $this->assertStringContainsString('value="A frame, 2026"', $html);
    }

    #[Test]
    public function it_carries_the_antispam_the_visitor_never_sees(): void
    {
        $this->form();

        $html = $this->render();

        // Clipped rather than hidden: the simpler robots skip what the page calls hidden.
        $this->assertStringContainsString('name="webx_hp"', $html);
        $this->assertStringContainsString('clip:rect(0 0 0 0)', $html);
        $this->assertStringContainsString('name="webx_ts"', $html);
        $this->assertStringContainsString('name="webx_page"', $html);
    }

    #[Test]
    public function a_form_may_ask_for_no_honeypot_at_all(): void
    {
        $this->form('contact', [], ['antispam.honeypot' => false]);

        $this->assertStringNotContainsString('webx_hp', $this->render());
    }

    #[Test]
    public function it_draws_the_captcha_a_form_asks_for(): void
    {
        config()->set('webx-inbox.captcha.turnstile.key', 'site-key');

        $this->form('contact', [], ['antispam.captcha' => 'turnstile']);

        $html = $this->render();

        $this->assertStringContainsString('class="cf-turnstile" data-sitekey="site-key"', $html);
        $this->assertStringContainsString('challenges.cloudflare.com/turnstile/v0/api.js', $html);
    }

    #[Test]
    public function a_captcha_the_site_has_no_key_for_is_not_drawn(): void
    {
        // The form would refuse every submission anyway — the verifier has no secret either —
        // so the page keeps quiet and the log does not.
        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        $this->assertStringNotContainsString('g-recaptcha', $this->render());
    }

    #[Test]
    public function the_enhancement_script_is_linked_once_per_page(): void
    {
        $this->form('first', [['name' => 'name', 'type' => FieldType::Text]]);
        $this->form('second', [['name' => 'name', 'type' => FieldType::Text]]);

        $html = $this->render('<x-webx-form slug="first" /><x-webx-form slug="second" />');

        $this->assertSame(1, substr_count($html, 'inbox.js'));
    }

    #[Test]
    public function the_script_is_served_with_its_contents_for_a_version(): void
    {
        $this->form();

        preg_match('/src="([^"]*inbox\.js[^"]*)"/', $this->render(), $matches);

        $this->assertNotEmpty($matches, 'the form did not link the script');

        $response = $this->get(html_entity_decode($matches[1]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
        $this->assertStringContainsString('data-webx-form', $response->getContent() ?: '');
    }

    #[Test]
    public function a_site_that_bundles_the_script_itself_is_not_given_ours(): void
    {
        config()->set('webx-inbox.script', false);

        $this->form();

        $this->assertStringNotContainsString('inbox.js', $this->render());
    }

    #[Test]
    public function the_submit_button_says_what_the_form_says_it_says(): void
    {
        $this->form('contact', [], ['design.submit-text' => ['en' => 'Ask for a quote']]);

        $this->assertStringContainsString('>Ask for a quote</button>', $this->render());
    }

    #[Test]
    public function a_submission_without_javascript_comes_back_to_the_page_it_was_sent_from(): void
    {
        $this->form('contact', [], ['thank-you.heading' => ['en' => 'Thank you']]);

        $response = $this->post($this->intake(), [
            'webx_form' => 'contact',
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello'],
        ], ['referer' => 'http://localhost/contact']);

        $response->assertRedirect('http://localhost/contact');
        $response->assertSessionHas('webx-inbox', fn (array $flash): bool => $flash['form'] === 'contact'
            && $flash['heading'] === 'Thank you');

        $this->assertSame(1, Submission::query()->count());
    }

    #[Test]
    public function the_form_then_shows_the_thank_you_it_was_given(): void
    {
        $this->form();

        $html = $this->withSession(['webx-inbox' => [
            'ok' => true,
            'form' => 'contact',
            'heading' => 'Thank you',
            'message' => '<p>We will write back.</p>',
            'redirect' => null,
        ]])->render();

        $this->assertStringContainsString('Thank you', $html);
        $this->assertStringContainsString('<p>We will write back.</p>', $html);
        $this->assertStringNotContainsString('data-webx-message hidden', $html);
    }

    #[Test]
    public function the_thank_you_of_another_form_on_the_page_is_not_this_ones(): void
    {
        $this->form('contact');
        $this->form('subscribe', [['name' => 'email', 'type' => FieldType::Email]]);

        $html = $this->withSession(['webx-inbox' => [
            'ok' => true,
            'form' => 'subscribe',
            'heading' => 'Subscribed',
            'message' => null,
            'redirect' => null,
        ]])->render('<x-webx-form slug="contact" />');

        $this->assertStringNotContainsString('Subscribed', $html);
    }

    #[Test]
    public function a_refused_submission_without_javascript_comes_back_with_the_errors_under_the_fields(): void
    {
        $this->form();

        $response = $this->post($this->intake(), [
            'webx_form' => 'contact',
            'fields' => ['email' => 'not-an-address'],
        ], ['referer' => 'http://localhost/contact']);

        $response->assertRedirect('http://localhost/contact');
        $response->assertSessionHasErrors(['fields.name', 'fields.email']);
    }

    #[Test]
    public function the_form_then_shows_them_and_keeps_what_was_typed(): void
    {
        $this->form();

        $html = $this->withSession([
            'errors' => (new ViewErrorBag)->put('default', new MessageBag([
                'fields.email' => ['The e-mail must be a valid address.'],
            ])),
            '_old_input' => [
                'webx_form' => 'contact',
                'fields' => ['name' => 'Ada', 'email' => 'not-an-address'],
            ],
        ])->render();

        $this->assertStringContainsString('The e-mail must be a valid address.', $html);
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);

        // Nobody should have to type it all again to fix one line of it.
        $this->assertStringContainsString('value="Ada"', $html);
        $this->assertStringContainsString('value="not-an-address"', $html);
    }

    #[Test]
    public function the_errors_of_another_form_on_the_page_are_not_this_ones(): void
    {
        $this->form('contact');
        $this->form('subscribe', [['name' => 'email', 'type' => FieldType::Email, 'is_required' => true]]);

        $html = $this->withSession([
            'errors' => (new ViewErrorBag)->put('default', new MessageBag([
                'fields.email' => ['The e-mail is required.'],
            ])),
            '_old_input' => ['webx_form' => 'subscribe', 'fields' => []],
        ])->render('<x-webx-form slug="contact" />');

        $this->assertStringNotContainsString('The e-mail is required.', $html);
        $this->assertStringNotContainsString('is-invalid', $html);
    }

    /**
     * The form as a page of the site serves it, which is the only way to see what the session
     * does to it: `$errors` and the old input arrive through the `web` group, and a form
     * rendered outside a request has neither.
     */
    private function render(string $blade = '<x-webx-form slug="contact" />'): string
    {
        // A response prints the script once, and each of these is a response of its own.
        $this->app->forgetScopedInstances();

        Route::middleware('web')->get('/page', static fn (): string => Blade::render($blade));

        return $this->get('/page')->assertOk()->getContent() ?: '';
    }
}
