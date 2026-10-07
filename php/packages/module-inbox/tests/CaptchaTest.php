<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Models\Submission;

/**
 * The captcha at the door (§7): each kind of key, what the visitor is told, and what the log
 * says about why.
 */
final class CaptchaTest extends TestCase
{
    private const VERIFY = 'https://www.google.com/recaptcha/api/siteverify';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('webx-inbox.captcha.recaptcha.key', 'site-key');
        config()->set('webx-inbox.captcha.recaptcha.secret', 'site-secret');
        config()->set('webx-inbox.captcha.turnstile.key', 'turnstile-key');
        config()->set('webx-inbox.captcha.turnstile.secret', 'turnstile-secret');
    }

    #[Test]
    public function a_checkbox_left_unticked_is_told_to_tick_it_beside_the_widget(): void
    {
        Log::spy();
        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        $this->postJson($this->intake(), $this->valid())
            ->assertStatus(422)
            ->assertJsonPath('errors.captcha.0', 'Please confirm you are not a robot and send the form again.')
            ->assertJsonMissingPath('errors.form');

        $this->assertRefusedFor('captcha_missing');
        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function an_invisible_captcha_without_an_answer_is_a_page_without_javascript(): void
    {
        config()->set('webx-inbox.captcha.recaptcha.type', 'invisible');
        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        $this->postJson($this->intake(), $this->valid())
            ->assertStatus(422)
            ->assertJsonPath('errors.captcha.0', 'This form checks that you are not a robot with JavaScript. Please turn it on and send the form again.');
    }

    #[Test]
    public function an_invisible_turnstile_without_an_answer_is_told_the_same(): void
    {
        config()->set('webx-inbox.captcha.turnstile.mode', 'invisible');
        $this->form('contact', [], ['antispam.captcha' => 'turnstile']);

        $this->postJson($this->intake(), $this->valid())
            ->assertStatus(422)
            ->assertJsonPath('errors.captcha.0', 'This form checks that you are not a robot with JavaScript. Please turn it on and send the form again.');
    }

    #[Test]
    public function a_failed_answer_logs_what_the_provider_said_and_nothing_secret(): void
    {
        Log::spy();
        Http::fake([self::VERIFY => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        $this->postJson($this->intake(), $this->valid(['g-recaptcha-response' => 'the-token']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['captcha']);

        $context = $this->assertRefusedFor('captcha_failed');
        $this->assertSame(['invalid-input-response'], $context['error-codes']);
        $this->assertStringNotContainsString('the-token', (string) json_encode($context));
        $this->assertStringNotContainsString('site-secret', (string) json_encode($context));

        // Verified with the secret and the visitor's address.
        Http::assertSent(static fn (ClientRequest $request): bool => $request['secret'] === 'site-secret'
            && $request['response'] === 'the-token'
            && $request['remoteip'] === '127.0.0.1');
    }

    #[Test]
    public function a_good_answer_lets_the_submission_in(): void
    {
        Http::fake([self::VERIFY => Http::response(['success' => true])]);
        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        $this->postJson($this->intake(), $this->valid(['g-recaptcha-response' => 'the-token']))->assertOk();

        $this->assertSame(1, Submission::query()->count());
    }

    #[Test]
    public function v3_passes_a_score_above_the_line_for_this_forms_action(): void
    {
        config()->set('webx-inbox.captcha.recaptcha.type', 'v3');
        Http::fake([self::VERIFY => Http::response(['success' => true, 'score' => 0.9, 'action' => 'webx_form_contact_us'])]);
        $this->form('contact-us', [], ['antispam.captcha' => 'recaptcha']);

        $this->postJson($this->intake('contact-us'), $this->valid(['g-recaptcha-response' => 'the-token']))->assertOk();
    }

    #[Test]
    public function v3_refuses_a_low_score_with_the_sentence_everybody_gets(): void
    {
        Log::spy();
        config()->set('webx-inbox.captcha.recaptcha.type', 'v3');
        config()->set('webx-inbox.captcha.recaptcha.min_score', 0.5);
        Http::fake([self::VERIFY => Http::response(['success' => true, 'score' => 0.1, 'action' => 'webx_form_contact'])]);
        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        // A score is not something the visitor can do anything about, so nothing names it.
        $this->postJson($this->intake(), $this->valid(['g-recaptcha-response' => 'the-token']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['form']);

        $context = $this->assertRefusedFor('captcha_failed');
        $this->assertSame(0.1, $context['score']);
        $this->assertSame(0.5, $context['min_score']);
    }

    #[Test]
    public function v3_refuses_a_token_minted_for_another_action(): void
    {
        config()->set('webx-inbox.captcha.recaptcha.type', 'v3');
        Http::fake([self::VERIFY => Http::response(['success' => true, 'score' => 0.9, 'action' => 'login'])]);
        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        $this->postJson($this->intake(), $this->valid(['g-recaptcha-response' => 'the-token']))->assertStatus(422);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function a_captcha_without_a_secret_refuses_without_blaming_the_visitor(): void
    {
        Log::spy();
        config()->set('webx-inbox.captcha.recaptcha.secret', null);
        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);

        $this->postJson($this->intake(), $this->valid(['g-recaptcha-response' => 'the-token']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['form']);

        $this->assertRefusedFor('captcha_unconfigured');
    }

    #[Test]
    public function the_other_layers_name_themselves_in_the_log_and_not_to_the_visitor(): void
    {
        Log::spy();
        $this->form('contact', [], ['antispam.min_seconds' => 3]);

        $this->postJson($this->intake(), $this->valid(['webx_ts' => Crypt::encrypt((string) time())]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['form']);

        $this->assertRefusedFor('too_fast');

        $this->form('callback');

        $this->postJson($this->intake('callback'), $this->valid(), ['Origin' => 'https://not-this-site.test'])->assertStatus(422);

        $this->assertRefusedFor('origin');
    }

    #[Test]
    public function the_panel_is_told_what_the_site_has_for_each_captcha(): void
    {
        config()->set('webx-inbox.captcha.recaptcha.type', 'invisible');
        config()->set('webx-inbox.captcha.turnstile.secret', '');
        $form = $this->form();

        $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('forms/'.$form->id))
            ->assertOk()
            ->assertJsonPath('data.captcha.recaptcha', ['type' => 'invisible', 'configured' => true])
            ->assertJsonPath('data.captcha.turnstile', ['type' => 'checkbox', 'configured' => false]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function valid(array $extra = []): array
    {
        return ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test'], ...$extra];
    }

    /**
     * The refusal line was written with this reason; its context, for a closer look.
     *
     * @return array<string, mixed>
     */
    private function assertRefusedFor(string $reason): array
    {
        $seen = null;

        Log::getFacadeRoot()->shouldHaveReceived('info')->withArgs(static function (string $message, array $context = []) use ($reason, &$seen): bool {
            if (! str_contains($message, 'refused by the antispam') || ($context['reason'] ?? null) !== $reason) {
                return false;
            }

            $seen = $context;

            return true;
        })->atLeast()->once();

        $this->assertIsArray($seen);

        return $seen;
    }
}
