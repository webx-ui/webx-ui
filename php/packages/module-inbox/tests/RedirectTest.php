<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Inbox\Tests\Fixtures\TranslatedPages;

/**
 * Where the visitor goes after sending (§5): a link of the panel, opened in the language the form
 * was sent in. A site in English, its main language, and Russian, with the language in the path.
 */
final class RedirectTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'ru'],
        ]);
        $app['config']->set('webx-localization.strategy', 'prefix');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(LinkSources::class)->register(new TranslatedPages);
    }

    #[Test]
    public function a_page_opens_in_the_language_the_form_was_sent_in(): void
    {
        $this->form('contact', [], ['redirect' => $this->page(7)]);

        $this->send('ru')->assertJsonPath('redirect', 'https://example.test/ru/spasibo');
        $this->send('en')->assertJsonPath('redirect', 'https://example.test/thank-you');
    }

    #[Test]
    public function a_page_without_the_translation_opens_in_the_main_language(): void
    {
        $this->form('contact', [], ['redirect' => $this->page(8)]);

        $this->send('ru')->assertJsonPath('redirect', 'https://example.test/english-only');
    }

    #[Test]
    public function a_page_that_is_not_on_the_site_is_no_place_to_send_anybody(): void
    {
        $this->form('contact', [], ['redirect' => $this->page(9), 'thank-you.heading' => ['en' => 'Thanks', 'ru' => 'Спасибо']]);

        $this->send('ru')->assertJsonPath('redirect', null)->assertJsonPath('heading', 'Спасибо');

        $this->form('callback', [], ['redirect' => $this->page(404)]);

        $this->postJson($this->intake('callback'), ['fields' => ['name' => 'Ada', 'email' => 'ada@site.test'], 'webx_locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('redirect', null);
    }

    #[Test]
    public function a_path_of_this_site_gets_the_language_of_the_visitor_and_another_site_is_left_alone(): void
    {
        $this->form('contact', [], ['redirect' => ['target' => 'url', 'url' => '/thank-you', 'hash' => 'top']]);

        $this->send('ru')->assertJsonPath('redirect', url('/ru/thank-you').'#top');
        $this->send('en')->assertJsonPath('redirect', url('/thank-you').'#top');

        $this->form('callback', [], ['redirect' => ['target' => 'url', 'url' => 'https://elsewhere.test/done']]);

        $this->postJson($this->intake('callback'), ['fields' => ['name' => 'Ada', 'email' => 'ada@site.test'], 'webx_locale' => 'ru'])
            ->assertJsonPath('redirect', 'https://elsewhere.test/done');
    }

    #[Test]
    public function an_address_saved_before_the_choice_still_works(): void
    {
        $this->form('contact', [], ['redirect' => '/thank-you']);

        $this->send('ru')->assertJsonPath('redirect', url('/ru/thank-you'));
    }

    #[Test]
    public function nowhere_is_the_thank_you_in_place_of_the_form(): void
    {
        $this->form('contact', [], ['redirect' => ['target' => 'none']]);

        $this->send('en')->assertJsonPath('redirect', null);
    }

    #[Test]
    public function the_editor_saves_a_link_and_refuses_one_that_would_go_nowhere_good(): void
    {
        $form = $this->form('contact');
        $save = fn (mixed $redirect) => $this->actingAs($this->editor(), 'cms')->putJson($this->api("forms/{$form->id}"), [
            'slug' => 'contact',
            'title' => ['en' => 'Contact'],
            'options' => ['redirect' => $redirect],
        ]);

        $save([...$this->page(7), 'new_tab' => true, 'rel' => ['nofollow']])
            ->assertOk()
            // A redirect is not clicked: no new tab, no rel.
            ->assertJsonPath('data.options.redirect', [
                'target' => 'entity', 'entity_type' => 'page', 'entity_id' => 7,
                'url' => null, 'hash' => null, 'new_tab' => false, 'rel' => [],
            ]);

        $save('/thanks')->assertOk()->assertJsonPath('data.options.redirect.target', 'url')->assertJsonPath('data.options.redirect.url', '/thanks');

        $save(['target' => 'none'])->assertOk()->assertJsonMissingPath('data.options.redirect');

        $save(['target' => 'url', 'url' => 'javascript:alert(1)'])->assertStatus(422)->assertJsonValidationErrors(['options.redirect']);
        $save(['target' => 'entity', 'entity_type' => 'unknown', 'entity_id' => 1])->assertStatus(422)->assertJsonValidationErrors(['options.redirect']);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(int $id): array
    {
        return ['target' => 'entity', 'entity_type' => 'page', 'entity_id' => $id];
    }

    /**
     * @return TestResponse<Response>
     */
    private function send(string $locale): TestResponse
    {
        return $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@site.test'], 'webx_locale' => $locale])->assertOk();
    }
}
