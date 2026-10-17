<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Manifest\Branding;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Admin\Tests\Fixtures\PagesModule;
use WebxUi\Cache\CacheGroups;

/**
 * The manifest's half of words and the site lives on a versioned key (WEBX_UI_PERFORMANCE_AUDIT.md
 * §9.5): what is in it must be the same for every administrator who reads the panel in one
 * language, and what is not the same — a module's `meta`, which may be about the person — must
 * never be read from it.
 */
final class ManifestCacheTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-cache.enabled', true);
    }

    #[Test]
    public function what_a_module_says_about_the_person_is_never_handed_to_another(): void
    {
        $this->register(new PagesModule, $this->secretive());

        $this->actingAs(new Editor(['vault.open'], id: 1));
        $this->assertSame(['inside' => true], $this->vault()['meta']);

        // Warm now: the second administrator reads the words from the cache and their own meta.
        $this->actingAs(new Editor([], id: 2));
        $this->assertSame([], $this->vault()['meta']);

        $this->actingAs(new Editor(['vault.open'], id: 1));
        $this->assertSame(['inside' => true], $this->vault()['meta']);
    }

    #[Test]
    public function the_words_are_read_once_and_follow_what_they_are_made_of(): void
    {
        $branding = new class implements BrandingSource
        {
            public int $asked = 0;

            public string $title = 'First name';

            public function branding(): Branding
            {
                $this->asked++;

                return new Branding(title: $this->title);
            }
        };
        $this->app->instance(BrandingSource::class, $branding);
        $this->register(new PagesModule);

        $this->getJson('/api/cms/manifest')->assertOk()->assertJsonPath('data.title', 'First name');
        $this->getJson('/api/cms/manifest')->assertOk()->assertJsonPath('data.title', 'First name');
        $this->assertSame(1, $branding->asked);

        // A group it depends on moved — the settings, on a site that has them, move on their own.
        $branding->title = 'Second name';
        $this->app->make(CacheGroups::class)->bump(CacheGroups::SCREENS);
        $this->getJson('/api/cms/manifest')->assertOk()->assertJsonPath('data.title', 'Second name');

        // The panel's config is in the name: a deploy that renames the groups is seen at once.
        config()->set('webx-admin.groups', ['content' => ['title' => 'Everything', 'order' => 1]]);
        $this->getJson('/api/cms/manifest')->assertOk()->assertJsonPath('data.groups.0.title', 'Everything');

        // So is a module installed after the words were cached.
        $this->register($this->secretive());
        $this->assertContains('vault', array_column((array) $this->getJson('/api/cms/manifest')->json('data.modules'), 'id'));
    }

    #[Test]
    public function the_home_is_the_one_of_the_host_asked(): void
    {
        $this->get('http://one.test/api/cms/manifest')->assertOk()->assertJsonPath('data.siteUrl', 'http://one.test');
        $this->get('http://two.test/api/cms/manifest')->assertOk()->assertJsonPath('data.siteUrl', 'http://two.test');
    }

    private function secretive(): AbstractModule
    {
        return new class extends AbstractModule
        {
            public function id(): string
            {
                return 'vault';
            }

            public function permissions(): array
            {
                return ['vault.open'];
            }

            public function manifest(): array
            {
                $user = request()->user();

                return $user instanceof HasPermissions && $user->hasPermission('vault.open') ? ['inside' => true] : [];
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function vault(): array
    {
        foreach ((array) $this->getJson('/api/cms/manifest')->assertOk()->json('data.modules') as $module) {
            if (is_array($module) && ($module['id'] ?? null) === 'vault') {
                return $module;
            }
        }

        $this->fail('The module is not in the manifest.');
    }
}
