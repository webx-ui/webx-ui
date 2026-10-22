<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Submission;

/**
 * `<x-webx-inbox::form>` — the form of the panel, printed on the site (§10), drawn by the widgets'
 * form engine since W6.4 (WIDGETS §17): `<x-webx-form :csrf="false">`, an `<x-webx-field>` per
 * field, the intake's rules as checks in the browser, `FormResponse` for the answer.
 *
 * Most of it is about the half that works with JavaScript switched off: the markup, what the
 * fields become, and what comes back through the session after a plain POST.
 */
final class PublicFormTest extends TestCase
{
    #[Test]
    public function it_prints_a_form_of_the_engine_that_posts_to_the_intake_without_a_token(): void
    {
        $this->form();

        $html = $this->render();

        $this->assertMatchesRegularExpression('~<form id="webx-inbox-contact" action="'.preg_quote(url($this->intake()), '~').'" method="post" data-webx-send="" class="webx-form webx-inbox-form" data-webx-form="contact">~', $html);

        // The intake guards itself (decision 8): no token, no route to renew one.
        $this->assertStringNotContainsString('name="_token"', $html);
        $this->assertStringNotContainsString('data-webx-csrf', $html);

        // Which form of the page it is, for the engine, and which form of the panel, for the intake.
        $this->assertStringContainsString('<input type="hidden" name="_webx_form" value="webx-inbox-contact">', $html);
        $this->assertStringContainsString('name="webx_form" value="contact"', $html);

        // The names the intake validates under: an error lands beneath the input that caused it.
        foreach (['name', 'email', 'message'] as $key) {
            $this->assertStringContainsString('data-webx-field="fields.'.$key.'"', $html);
            $this->assertStringContainsString('name="fields['.$key.']"', $html);
        }

        // The places of the answer, the shell's own; the button, its own too.
        $this->assertStringContainsString('<div class="webx-form__message" role="status" aria-live="polite"></div>', $html);
        $this->assertStringContainsString('<button type="submit" class="webx-form__submit">Send</button>', $html);
        $this->assertStringNotContainsString('wx-form', $html);
    }

    #[Test]
    public function it_prints_nothing_for_a_form_that_is_not_there_or_is_switched_off(): void
    {
        $this->form('contact')->update(['is_enabled' => false]);

        $this->assertSame('', trim($this->render()));
        $this->assertSame('', trim($this->render('<x-webx-inbox::form slug="nothing-like-it" />')));
    }

    #[Test]
    public function every_type_of_the_panel_is_a_field_of_the_widgets(): void
    {
        $this->everything();

        $html = $this->render('<x-webx-inbox::form slug="everything" />');

        foreach (['line' => 'text', 'mail' => 'email', 'phone' => 'tel', 'letter' => 'textarea', 'plan' => 'select', 'when' => 'date', 'brief' => 'file'] as $key => $type) {
            $this->assertMatchesRegularExpression('~<div class="webx-field webx-field--'.$type.'[^"]*" data-webx-field="fields\.'.$key.'"~', $html, $type);
        }

        // A group of dots or ticks is a <fieldset> with its <legend>; the ticks send a list.
        $this->assertMatchesRegularExpression('~<fieldset[^>]*class="webx-field webx-field--radio[^"]*" data-webx-field="fields\.reach"~', $html);
        $this->assertMatchesRegularExpression('~<fieldset[^>]*class="webx-field webx-field--checkbox[^"]*" data-webx-field="fields\.extras"~', $html);
        $this->assertStringContainsString('<legend class="webx-field__label webx-field__label--legend">Extras</legend>', $html);
        $this->assertStringContainsString('name="fields[extras][]"', $html);

        $this->assertStringContainsString('rows="8"', $html);
        $this->assertStringContainsString('<option value="basic"  >Basic</option>', $html);
        $this->assertStringContainsString('min="2026-01-01"', $html);
        $this->assertStringContainsString('accept=".pdf"', $html);
        $this->assertStringContainsString('<input type="hidden" id="webx-inbox-everything-product" name="fields[product]"', $html);

        // A consent is a sentence with a link in it, written in the panel: printed as it is, beside its tick.
        $this->assertMatchesRegularExpression('~webx-field__choice--consent.*<span class="webx-field__choice-label">I agree to the <a href="/privacy">privacy policy</a>~s', $html);

        // A file field on the form makes the form itself carry files.
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    #[Test]
    public function the_intakes_own_rules_are_the_checks_in_the_browser_in_the_validators_words(): void
    {
        $this->everything();

        $html = html_entity_decode($this->render('<x-webx-inbox::form slug="everything" />'), ENT_QUOTES);

        // The words of the 422, the field's own label in them (Rules::attributes()).
        $this->assertStringContainsString('{"rule":"max","as":"string","value":40,"message":"The Line field must not be greater than 40 characters."}', $html);
        $this->assertStringContainsString('{"rule":"email","message":"The Mail field must be a valid email address."}', $html);
        $this->assertStringContainsString('{"rule":"regex","source":"^(?:[0-9 +()-]{6,})$","flags":"u","message":"The Phone field format is invalid."}', $html);
        $this->assertStringContainsString('{"rule":"in","values":["basic"],"message":"The selected Plan is invalid."}', $html);
        $this->assertStringContainsString('{"rule":"accepted","message":"The Terms field must be accepted."}', $html);
        $this->assertStringContainsString('{"rule":"mimes","values":["pdf"],"message":"The Brief field must be a file of type: pdf."}', $html);
        $this->assertStringContainsString('{"rule":"after_or_equal","value":"2026-01-01","message":"The When field must be a date after or equal to 2026-01-01."}', $html);

        // Required by a rule, so marked so; a consent by `accepted`.
        $this->assertMatchesRegularExpression('~id="webx-inbox-everything-terms" name="fields\[terms\]" value="1"\s+required~', $html);

        // `maxlength` and `pattern` are not printed: they cut a paste silently or bubble in the
        // browser's language — the checks say the server's words instead (W6.3).
        $this->assertStringNotContainsString('maxlength=', $html);
        $this->assertStringNotContainsString('pattern=', $html);
    }

    #[Test]
    public function a_check_in_the_browser_is_word_for_word_what_the_intake_answers(): void
    {
        $this->form();

        preg_match('~data-webx-field="fields\.email" data-webx-rules="([^"]+)"~', $this->render(), $rules);
        $checks = json_decode(html_entity_decode($rules[1] ?? '[]', ENT_QUOTES), true);

        $response = $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'not-an-address']])->assertStatus(422);

        $this->assertContains($response->json('errors')['fields.email'][0], array_column($checks, 'message'));
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

        $html = $this->render('<x-webx-inbox::form slug="survey" />');

        $this->assertStringContainsString('name="fields[extras][]"', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*type="checkbox"[^>]*\srequired/s', $html);
    }

    #[Test]
    public function a_select_with_nothing_to_choose_from_is_left_out_not_the_page(): void
    {
        $this->form('contact', [
            ['name' => 'name', 'type' => FieldType::Text],
            ['name' => 'plan', 'type' => FieldType::Select, 'options' => ['choices' => []]],
        ]);

        Log::shouldReceive('warning')->once()->withArgs(static fn (string $message): bool => str_contains($message, 'nothing to choose from'));

        $html = $this->render();

        $this->assertStringContainsString('name="fields[name]"', $html);
        $this->assertStringNotContainsString('fields[plan]', $html);
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
    public function it_says_which_language_the_page_was_printed_in(): void
    {
        $this->form();

        $this->assertStringContainsString('<input type="hidden" name="webx_locale" value="en">', $this->render());
    }

    #[Test]
    public function a_placement_is_a_class_and_a_hidden_field_and_not_an_attribute(): void
    {
        $this->form();

        $html = $this->render('<x-webx-inbox::form slug="contact" placement="footer" />');

        $this->assertStringContainsString('class="webx-form webx-inbox-form webx-inbox-form--footer"', $html);
        $this->assertStringContainsString('<input type="hidden" name="webx_placement" value="footer">', $html);
        $this->assertStringNotContainsString('placement="footer"', $html);

        $plain = $this->render();
        $this->assertStringContainsString('class="webx-form webx-inbox-form"', $plain);
        $this->assertStringNotContainsString('webx_placement', $plain);
    }

    #[Test]
    public function a_placement_that_is_not_a_name_is_dropped_with_a_warning(): void
    {
        $this->form();

        Log::shouldReceive('warning')->once()->withArgs(static fn (string $message): bool => str_contains($message, 'placement'));

        $html = $this->render('<x-webx-inbox::form slug="contact" placement="Footer Area" />');

        // The form is still there — it matters more than a typo in one of its attributes.
        $this->assertStringContainsString('class="webx-form webx-inbox-form"', $html);
        $this->assertStringNotContainsString('webx_placement', $html);
        $this->assertStringNotContainsString('Footer Area', $html);
    }

    #[Test]
    public function a_field_that_is_not_full_width_says_so_in_its_class(): void
    {
        $form = $this->form('contact', [['name' => 'name', 'type' => FieldType::Text, 'is_fullsize' => false]]);
        $this->field($form, ['name' => 'message', 'type' => FieldType::Textarea]);

        $html = $this->render();

        $this->assertMatchesRegularExpression('~class="webx-field webx-field--text webx-inbox-form__field webx-inbox-form__field--half" data-webx-field="fields\.name"~', $html);
        $this->assertMatchesRegularExpression('~class="webx-field webx-field--textarea webx-inbox-form__field" data-webx-field="fields\.message"~', $html);
    }

    #[Test]
    public function the_site_fills_the_hidden_fields_by_their_machine_name(): void
    {
        $this->form('contact', [
            ['name' => 'name', 'type' => FieldType::Text],
            ['name' => 'product', 'type' => FieldType::Hidden],
        ]);

        $html = $this->render('<x-webx-inbox::form slug="contact" :values="[\'product\' => \'A frame, 2026\']" />');

        $this->assertStringContainsString('name="fields[product]" value="A frame, 2026"', $html);
    }

    #[Test]
    public function it_carries_the_antispam_the_visitor_never_sees(): void
    {
        $this->form();

        $html = $this->render();

        // Clipped rather than hidden: the simpler robots skip what the page calls hidden.
        $this->assertStringContainsString('<div class="webx-inbox-form__honeypot" aria-hidden="true" style="position:absolute;', $html);
        $this->assertStringContainsString('name="webx_hp"', $html);
        $this->assertStringContainsString('clip:rect(0 0 0 0)', $html);
        $this->assertStringContainsString('name="webx_ts"', $html);
        $this->assertStringContainsString('name="webx_page"', $html);
    }

    #[Test]
    public function the_same_form_twice_on_a_page_repeats_no_id(): void
    {
        $this->form();

        // A subscription in the footer and the same one in a popup.
        $html = $this->render('<x-webx-inbox::form slug="contact" placement="footer" /><x-webx-inbox::form slug="contact" placement="popup" />');

        preg_match_all('/\sid="([^"]+)"/', $html, $matches);
        $ids = $matches[1];

        $this->assertNotSame([], $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));

        $this->assertStringContainsString('<form id="webx-inbox-contact"', $html);
        $this->assertStringContainsString('<form id="webx-inbox-contact-2"', $html);
        $this->assertStringContainsString('id="webx-inbox-contact-name"', $html);
        $this->assertStringContainsString('id="webx-inbox-contact-2-name"', $html);
        $this->assertStringContainsString('<label for="webx-inbox-contact-2-hp">', $html);
        $this->assertSame(2, substr_count($html, 'name="webx_hp"'));
    }

    #[Test]
    public function a_form_may_ask_for_no_honeypot_at_all(): void
    {
        $this->form('contact', [], ['antispam.honeypot' => false]);

        $this->assertStringNotContainsString('webx_hp', $this->render());
    }

    #[Test]
    public function it_draws_the_captcha_a_form_asks_for_in_a_place_its_refusal_goes_to(): void
    {
        config()->set('webx-inbox.captcha.turnstile.key', 'site-key');

        $this->form('contact', [], ['antispam.captcha' => 'turnstile']);

        $html = $this->render();

        $this->assertMatchesRegularExpression('~<div\s+class="webx-inbox-form__captcha"\s+data-webx-field="captcha"\s+data-webx-captcha="turnstile"~', $html);
        $this->assertStringContainsString('class="cf-turnstile" data-sitekey="site-key"', $html);
        $this->assertStringContainsString('<p class="webx-field__error" id="webx-inbox-contact-captcha-error"  hidden ></p>', $html);
        $this->assertStringContainsString('challenges.cloudflare.com/turnstile/v0/api.js', $html);

        // Inside the shell, before the button.
        $this->assertLessThan(strpos($html, 'webx-form__submit'), strpos($html, 'webx-inbox-form__captcha'));
    }

    #[Test]
    public function an_invisible_recaptcha_is_left_for_the_inbox_script_to_draw(): void
    {
        // Drawn by the provider's script as `g-recaptcha`, an Invisible key would want a global
        // callback — and drawn as a checkbox it is the widget's own "Invalid key type".
        config()->set('webx-inbox.captcha.recaptcha.key', 'site-key');
        config()->set('webx-inbox.captcha.recaptcha.type', 'invisible');

        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        $html = $this->render();

        $this->assertStringNotContainsString('class="g-recaptcha"', $html);
        $this->assertStringContainsString('data-webx-captcha-type="invisible"', $html);
        $this->assertStringContainsString('class="webx-inbox-form__captcha-widget" data-sitekey="site-key"', $html);
        $this->assertStringContainsString('src="https://www.google.com/recaptcha/api.js"', $html);
    }

    #[Test]
    public function an_invisible_turnstile_is_left_for_the_inbox_script_to_draw(): void
    {
        config()->set('webx-inbox.captcha.turnstile.key', 'site-key');
        config()->set('webx-inbox.captcha.turnstile.mode', 'invisible');

        $this->form('contact', [], ['antispam.captcha' => 'turnstile']);

        $html = $this->render();

        $this->assertStringNotContainsString('class="cf-turnstile"', $html);
        $this->assertStringContainsString('class="webx-inbox-form__captcha-widget" data-sitekey="site-key"', $html);
    }

    #[Test]
    public function recaptcha_v3_is_loaded_with_the_site_key_and_has_no_widget(): void
    {
        config()->set('webx-inbox.captcha.recaptcha.key', 'site-key');
        config()->set('webx-inbox.captcha.recaptcha.type', 'v3');

        $this->form('contact-us', [], ['antispam.captcha' => 'recaptcha']);

        $html = $this->render('<x-webx-inbox::form slug="contact-us" />');

        $this->assertStringContainsString('api.js?render=site-key', $html);
        $this->assertStringContainsString('<input type="hidden" name="g-recaptcha-response" value="">', $html);
        $this->assertStringContainsString('data-webx-captcha-action="webx_form_contact_us"', $html);
        $this->assertStringNotContainsString('data-sitekey', $html);
    }

    #[Test]
    public function a_captcha_refusal_without_javascript_comes_back_beside_the_widget_of_the_form_sent(): void
    {
        config()->set('webx-inbox.captcha.recaptcha.key', 'site-key');
        config()->set('webx-inbox.captcha.recaptcha.secret', 'site-secret');

        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        $this->post($this->intake(), [
            '_webx_form' => 'webx-inbox-contact',
            'webx_form' => 'contact',
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ], ['referer' => 'http://localhost/page'])->assertSessionHasErrors('captcha');

        $html = $this->render('<x-webx-inbox::form slug="contact" /><x-webx-inbox::form slug="contact" />');

        $this->assertStringContainsString('<p class="webx-field__error" id="webx-inbox-contact-captcha-error" >Please confirm you are not a robot', $html);
        // Not twice: the alert of the form shows what no field shows, and the captcha has its place.
        $this->assertSame(1, substr_count($html, '>Please confirm you are not a robot'));
        $this->assertStringContainsString('<p class="webx-field__error" id="webx-inbox-contact-2-captcha-error"  hidden ></p>', $html);
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
    public function the_inbox_script_comes_only_with_a_captcha_and_once_per_page(): void
    {
        $this->form('first', [['name' => 'name', 'type' => FieldType::Text]]);
        $this->form('second', [['name' => 'name', 'type' => FieldType::Text]]);

        // Sending is the widgets' form.js now: a form without a captcha needs nothing of ours.
        $this->assertStringNotContainsString('inbox.js', $this->render('<x-webx-inbox::form slug="first" /><x-webx-inbox::form slug="second" />'));

        config()->set('webx-inbox.captcha.turnstile.key', 'site-key');
        $this->form('third', [], ['antispam.captcha' => 'turnstile']);
        $this->form('fourth', [], ['antispam.captcha' => 'turnstile']);

        $this->assertSame(1, substr_count($this->render('<x-webx-inbox::form slug="third" /><x-webx-inbox::form slug="fourth" />'), '/inbox.js'));
    }

    #[Test]
    public function the_script_is_served_with_its_contents_for_a_version(): void
    {
        config()->set('webx-inbox.captcha.turnstile.key', 'site-key');
        $this->form('contact', [], ['antispam.captcha' => 'turnstile']);

        preg_match('/src="([^"]*inbox\.js[^"]*)"/', $this->render(), $matches);

        $this->assertNotEmpty($matches, 'the form did not link the script');

        $response = $this->get(html_entity_decode($matches[1]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
        $this->assertStringContainsString('webx:form:submit', $response->getContent() ?: '');
    }

    #[Test]
    public function a_site_that_bundles_the_script_itself_is_not_given_ours(): void
    {
        config()->set('webx-inbox.script', false);
        config()->set('webx-inbox.captcha.turnstile.key', 'site-key');

        $this->form('contact', [], ['antispam.captcha' => 'turnstile']);

        $this->assertStringNotContainsString('inbox.js', $this->render());
    }

    #[Test]
    public function the_submit_button_says_what_the_form_says_it_says(): void
    {
        $this->form('contact', [], ['design.submit-text' => ['en' => 'Ask for a quote']]);

        $this->assertStringContainsString('<button type="submit" class="webx-form__submit">Ask for a quote</button>', $this->render());
    }

    #[Test]
    public function the_intake_answers_the_script_with_form_response(): void
    {
        $this->form('contact', [], [
            'thank-you.heading' => ['en' => 'Thank you'],
            'thank-you.text' => ['en' => '<p>We will write back.</p>'],
        ]);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test']])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'heading' => 'Thank you', 'heading_html' => null, 'message' => '<p>We will write back.</p>', 'redirect' => null, 'html' => null]);
    }

    #[Test]
    public function a_form_with_no_thank_you_written_still_says_one(): void
    {
        // Neither heading nor text would be the engine's "only the event": the visitor told nothing.
        $this->form();

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test']])
            ->assertOk()
            ->assertJson(['heading' => 'Thank you. Your message has been sent.', 'message' => null, 'redirect' => null]);
    }

    #[Test]
    public function a_form_with_an_address_sends_the_visitor_there_both_ways(): void
    {
        $this->form('contact', [], ['redirect' => 'https://example.test/thanks']);
        $fields = ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test']];

        $this->postJson($this->intake(), $fields)->assertOk()->assertJson(['redirect' => 'https://example.test/thanks']);
        $this->post($this->intake(), $fields + ['_webx_form' => 'webx-inbox-contact'])->assertRedirect('https://example.test/thanks');
    }

    #[Test]
    public function a_submission_without_javascript_comes_back_to_the_page_and_the_form_it_was_sent_from(): void
    {
        $this->form('contact', [], ['thank-you.heading' => ['en' => 'Thank you'], 'thank-you.text' => ['en' => '<p>We will write back.</p>']]);

        $response = $this->post($this->intake(), [
            '_webx_form' => 'webx-inbox-contact-2',
            'webx_form' => 'contact',
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello'],
        ], ['referer' => 'http://localhost/contact']);

        $response->assertRedirect('http://localhost/contact');
        $response->assertSessionHas('webx-form', ['form' => 'webx-inbox-contact-2', 'heading' => 'Thank you', 'heading_html' => null, 'message' => '<p>We will write back.</p>', 'html' => null]);
        $response->assertSessionMissing('webx-inbox');
        $this->assertSame(1, Submission::query()->count());

        // The second copy on the page shows it, under its fields; the first stays a form.
        $html = $this->withSession(['webx-form' => ['form' => 'webx-inbox-contact-2', 'heading' => 'Thank you', 'message' => '<p>We will write back.</p>', 'html' => null]])
            ->render('<x-webx-inbox::form slug="contact" /><x-webx-inbox::form slug="contact" />');

        $this->assertSame(1, substr_count($html, '<p class="webx-form__heading">Thank you</p>'));
        $this->assertMatchesRegularExpression('~<form id="webx-inbox-contact-2"[^>]*class="webx-form is-sent webx-inbox-form"[^>]*data-webx-returned>~', $html);
        $this->assertGreaterThan(strpos($html, 'id="webx-inbox-contact-2"'), strpos($html, 'We will write back.'));
    }

    #[Test]
    public function a_refused_submission_without_javascript_comes_back_with_the_errors_under_the_fields(): void
    {
        $this->form();

        $response = $this->post($this->intake(), [
            '_webx_form' => 'webx-inbox-contact',
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
                '_webx_form' => 'webx-inbox-contact',
                'webx_form' => 'contact',
                'fields' => ['name' => 'Ada', 'email' => 'not-an-address'],
            ],
        ])->render();

        $this->assertMatchesRegularExpression('~<div class="webx-field webx-field--email is-invalid is-required webx-inbox-form__field" data-webx-field="fields\.email"~', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('<p class="webx-field__error" id="webx-inbox-contact-email-error" >The e-mail must be a valid address.</p>', $html);
        $this->assertStringContainsString('data-webx-returned', $html);

        // Nobody should have to type it all again to fix one line of it.
        $this->assertStringContainsString('value="Ada"', $html);
        $this->assertStringContainsString('value="not-an-address"', $html);
    }

    #[Test]
    public function an_antispam_refusal_without_javascript_is_said_in_the_alert_of_the_form_sent(): void
    {
        $this->form();

        $html = $this->withSession([
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['form' => ['The form could not be accepted.']])),
            '_old_input' => ['_webx_form' => 'webx-inbox-contact', 'webx_form' => 'contact', 'fields' => ['name' => 'Ada']],
        ])->render();

        $this->assertStringContainsString('<div class="webx-form__error" role="alert"', $html);
        $this->assertStringContainsString('<p class="webx-form__refusal">The form could not be accepted.</p>', $html);
    }

    #[Test]
    public function the_errors_and_the_input_of_another_form_on_the_page_are_not_this_ones(): void
    {
        // A contact form refused in the page's body, a subscription in its footer: both have an email.
        $this->form('contact');
        $this->form('subscribe', [['name' => 'email', 'type' => FieldType::Email, 'is_required' => true]]);

        $html = $this->withSession([
            'errors' => (new ViewErrorBag)->put('default', new MessageBag([
                'fields.email' => ['The e-mail is required.'],
            ])),
            '_old_input' => ['_webx_form' => 'webx-inbox-contact', 'webx_form' => 'contact', 'fields' => ['email' => 'ada@']],
        ])->render('<x-webx-inbox::form slug="subscribe" />');

        $this->assertStringNotContainsString('The e-mail is required.', $html);
        $this->assertStringNotContainsString('is-invalid', $html);
        $this->assertStringNotContainsString('ada@', $html);
        $this->assertStringNotContainsString('data-webx-returned', $html);
    }

    #[Test]
    public function a_view_published_before_the_engine_still_works_with_its_own_script(): void
    {
        // The alias period (WIDGETS §20, W6.4): a site that rewrote form.blade.php keeps a form.
        $this->app['view']->prependNamespace('webx-inbox', __DIR__.'/Fixtures/legacy-views');
        $this->form('contact', [], ['thank-you.heading' => ['en' => 'Thank you']]);

        $html = $this->render('<x-webx-inbox::form slug="contact" /><x-webx-inbox::form slug="contact" />');

        $this->assertStringContainsString('class="wx-form"', $html);
        $this->assertStringContainsString('id="wx-form-contact-name"', $html);
        $this->assertStringContainsString('id="wx-form-contact-2-name"', $html);
        $this->assertSame(1, substr_count($html, '/inbox-legacy.js'));
        $this->assertStringNotContainsString('/inbox.js', $html);

        // Its plain POST — no `_webx_form` — is thanked where that view reads it.
        $this->post($this->intake(), ['webx_form' => 'contact', 'fields' => ['name' => 'Ada', 'email' => 'ada@example.test']], ['referer' => 'http://localhost/contact'])
            ->assertRedirect('http://localhost/contact')
            ->assertSessionHas('webx-inbox', static fn (array $flash): bool => $flash['form'] === 'contact' && $flash['heading'] === 'Thank you');

        $thanked = $this->withSession(['webx-inbox' => ['ok' => true, 'form' => 'contact', 'heading' => 'Thank you', 'message' => null, 'redirect' => null]])->render();
        $this->assertStringNotContainsString('data-webx-message hidden', $thanked);

        // And the old script is served.
        preg_match('/src="([^"]*inbox-legacy\.js[^"]*)"/', $html, $matches);
        $this->assertStringContainsString('data-webx-form-error', (string) $this->get(html_entity_decode($matches[1]))->assertOk()->getContent());
    }

    /**
     * A form with a field of every type of the panel.
     */
    private function everything(): void
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
    }

    /**
     * The form as a page of the site serves it, which is the only way to see what the session
     * does to it: `$errors` and the old input arrive through the `web` group, and a form
     * rendered outside a request has neither.
     */
    private function render(string $blade = '<x-webx-inbox::form slug="contact" />'): string
    {
        // A response prints the script once, and each of these is a response of its own.
        $this->app->forgetScopedInstances();

        Route::middleware('web')->get('/page', static fn (): string => Blade::render($blade));

        return $this->get('/page')->assertOk()->getContent() ?: '';
    }
}
