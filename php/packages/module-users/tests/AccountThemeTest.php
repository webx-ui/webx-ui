<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Foundation\Application;
use Illuminate\View\DynamicComponent;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use WebxUi\Compositions\Compositions;
use WebxUi\Themes\ThemeLocator;

/**
 * The account in theme-default's `layout.account` (spec §11.4): the menu stands in the nav the
 * theme keeps for it by the module's patch, the grid gets the module's classes beside the
 * theme's, and «Confirm your e-mail» heads the content rather than standing under the grid.
 */
final class AccountThemeTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-themes.theme', 'webx-ui/theme-default');
        $app['config']->set('webx-users.layout', 'layout');
        // The header and the footer of the site: the theme's own read the menus of module-admin.
        $app['config']->set('view.paths', [str_replace('\\', '/', __DIR__).'/Fixtures/themed']);

        $app->resolving(ThemeLocator::class, static function (ThemeLocator $locator): void {
            $locator->register('webx-ui/theme-default', dirname(__DIR__, 2).'/theme-default');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::forgetComponents();

        // Compiled compositions outlive a run; yesterday's set would hide a change of the patch.
        $this->artisan('view:clear');
    }

    protected function tearDown(): void
    {
        self::forgetComponents();
        parent::tearDown();
    }

    #[Test]
    public function the_menu_stands_in_the_themes_account_layout(): void
    {
        $this->actingAs($this->visitor(), 'site');

        $html = (string) $this->get('/account/security')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~<div class="site-account webx-account">\s*<nav class="site-account__menu webx-account__nav" aria-label="Account">\s*<ul class="webx-account__menu">~',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '~<div class="site-account__body webx-account__main">\s*<div class="webx-users-verify"[^>]*>.*?</div>\s*<h1 class="webx-account__title">Sign-in and security</h1>~s',
            $html,
            'The banner of an unconfirmed address heads the content.',
        );
        $this->assertSame(1, substr_count($html, '<ul class="webx-account__menu">'), 'The frame does not print a second menu.');
        $this->assertStringContainsString('webx-account__item--security is-active', $html);
        $this->assertStringContainsString('site-header', $html);

        // The patch of the module lands whole: nothing of it is left out. (The SEO and the blocks
        // are not installed here — their directives are, rightly, the theme's findings.)
        $findings = array_values(array_filter(
            app(Compositions::class)->findings(),
            static fn ($finding): bool => ! $finding->note && (str_contains($finding->file, 'users') || str_contains($finding->operation, 'users') || str_contains($finding->operation, 'account')),
        ));
        $this->assertSame([], $findings);
    }

    /** `<x-dynamic-component>` remembers what it found in statics shared by every test. */
    private static function forgetComponents(): void
    {
        foreach (['compiler', 'componentClasses'] as $property) {
            $reflection = new ReflectionProperty(DynamicComponent::class, $property);
            $reflection->setValue(null, $property === 'compiler' ? null : []);
        }
    }
}
