<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

final class PanelLocaleTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.panel', ['en', 'ru', 'uk']);
    }

    #[Test]
    public function nobody_starts_with_a_language_chosen_for_them(): void
    {
        $this->actingAs($this->admin(), 'cms');

        $this->getJson('api/cms/auth/me')
            ->assertOk()
            ->assertJsonPath('data.locale', null);
    }

    #[Test]
    public function choosing_a_language_stores_it_on_the_person(): void
    {
        // On the person rather than in the browser, so it follows them to another machine and
        // so the server knows which language to write its own messages in.
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/locale', ['locale' => 'uk'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'uk');

        $this->assertSame('uk', $admin->fresh()?->locale);
    }

    #[Test]
    public function a_language_the_panel_has_no_words_for_is_refused(): void
    {
        // Storing it would leave somebody in an interface that falls back on every line —
        // which reads as a broken panel rather than as a missing translation.
        $this->actingAs($this->admin(), 'cms');

        $this->putJson('api/cms/auth/locale', ['locale' => 'fr'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('locale');
    }

    #[Test]
    public function the_server_answers_in_the_language_the_administrator_chose(): void
    {
        // The part that is easy to forget: menus come from the dictionary, but a 403 and a 422
        // are written by the server, and an English panel with Ukrainian errors under the
        // fields is worse than either on its own.
        Route::middleware(['web', 'cms.auth', 'webx.panel-locale', 'cms.can:pages.manage'])
            ->get('test/guarded', fn (): string => 'reached');

        $admin = $this->admin();
        $admin->forceFill(['locale' => 'ru'])->save();
        $this->actingAs($admin, 'cms');

        $this->getJson('test/guarded')
            ->assertForbidden()
            ->assertJsonPath('message', 'Этой учётной записи это не разрешено.');
    }

    #[Test]
    public function the_manifest_follows_the_administrators_own_language(): void
    {
        $admin = $this->admin(super: true);
        $admin->forceFill(['locale' => 'uk'])->save();
        $this->actingAs($admin, 'cms');

        $this->getJson('api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.locale', 'uk')
            // And a module's own name is translated with everything else.
            ->assertJsonPath('data.modules.0.title', 'Адміністратори');
    }
}
