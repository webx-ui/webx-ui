<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;

final class LocaleEndpointTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.panel', ['en', 'ru', 'uk']);
        $app['config']->set('webx-localization.locales', [
            ['code' => 'uk', 'default' => true],
            ['code' => 'en'],
        ]);
    }

    #[Test]
    public function the_languages_can_be_read_without_signing_in(): void
    {
        // The sign-in screen has to be drawn in something, and it is drawn before there is
        // a session to ask.
        $this->getJson('api/cms/locales')
            ->assertOk()
            ->assertJsonPath('data.fallback', 'en')
            ->assertJsonPath('data.panel.0.code', 'en')
            ->assertJsonPath('data.content.0.code', 'uk')
            ->assertJsonPath('data.content.0.nativeName', 'Українська')
            ->assertJsonPath('data.content.0.default', true);
    }

    #[Test]
    public function the_interface_dictionary_comes_from_the_packages_own_files(): void
    {
        $this->getJson('api/cms/translations/ru')
            ->assertOk()
            ->assertJsonPath('data.locale', 'ru')
            ->assertJsonPath('data.namespaces.webx-admin.shell.retry', 'Повторить')
            ->assertJsonPath('data.namespaces.webx-admin.nav.sections', 'Разделы');
    }

    #[Test]
    public function a_language_the_panel_does_not_speak_is_answered_in_one_it_does(): void
    {
        // A missing translation is not a missing page: a 404 here would leave the panel with
        // no words at all rather than with English ones.
        $this->getJson('api/cms/translations/fr')
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.namespaces.webx-admin.shell.retry', 'Try again');
    }

    #[Test]
    public function the_browsers_language_decides_before_anybody_has_chosen_one(): void
    {
        $this->getJson('api/cms/locales', ['Accept-Language' => 'uk-UA,uk;q=0.9'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'uk');

        // And an explicit ask from the sign-in screen beats the browser's habit.
        $this->getJson('api/cms/locales', ['X-Webx-Locale' => 'ru'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'ru');
    }

    #[Test]
    public function the_manifest_tells_the_panel_which_languages_content_is_written_in(): void
    {
        $this->getJson('api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.locales.0.code', 'uk')
            ->assertJsonPath('data.panelLocales.0.code', 'en')
            ->assertJsonPath('data.locale', 'en');
    }
}
