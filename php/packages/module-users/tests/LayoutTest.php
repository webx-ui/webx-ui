<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Foundation\Application;
use Illuminate\View\DynamicComponent;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use WebxUi\Users\Rendering\Layouts;

/**
 * Which layout a page stands in (spec §11.4): the site's `<x-layout>` with the handle `users.auth`
 * once `webx-users.layout` names it, the bare document of the module until then; the account in
 * `layout.account` where the site has it.
 */
final class LayoutTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The site's own views: a layout and its account variant, as a theme would bring them.
        $app['config']->set('view.paths', [str_replace('\\', '/', __DIR__).'/Fixtures/site']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::forgetComponents();
    }

    protected function tearDown(): void
    {
        self::forgetComponents();
        parent::tearDown();
    }

    #[Test]
    public function without_a_layout_named_the_page_is_its_own_document(): void
    {
        $this->assertSame(Layouts::STANDALONE, Layouts::auth());
        $this->assertSame(Layouts::STANDALONE, Layouts::account());

        $html = (string) $this->get('/account/sign-in')->getContent();

        $this->assertStringStartsWith('<!doctype html>', $html);
        $this->assertStringContainsString('<main id="content">', $html);
        $this->assertStringNotContainsString('data-layout', $html);
    }

    #[Test]
    public function the_sites_layout_gets_the_handle_of_the_sign_in(): void
    {
        config(['webx-users.layout' => 'layout']);

        $html = (string) $this->get('/account/register')->getContent();

        $this->assertStringContainsString('<body data-layout="layout" data-handle="users.auth">', $html);
        $this->assertStringContainsString('<title>Create an account', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertSame('layout.account', Layouts::account());
    }

    #[Test]
    public function a_component_named_for_the_account_wins(): void
    {
        config(['webx-users.layout' => 'layout', 'webx-users.account_layout' => 'shop-account']);

        $this->assertSame('shop-account', Layouts::account());
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
