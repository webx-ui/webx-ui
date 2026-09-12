<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Localization\Translations\DictionaryBuilder;

final class DictionaryBuilderTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.panel', ['en', 'uk']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('translator')->addNamespace('webx-test', __DIR__.'/Fixtures/lang');
        // A namespace that is not the panel's, to prove it stays out of the browser.
        $this->app->make('translator')->addNamespace('shop', __DIR__.'/Fixtures/lang');
    }

    private function builder(): DictionaryBuilder
    {
        return $this->app->make(DictionaryBuilder::class);
    }

    #[Test]
    public function it_collects_the_strings_a_package_ships(): void
    {
        $dictionary = $this->builder()->build('en');

        $this->assertSame('en', $dictionary['locale']);
        $this->assertSame('Sign in', $dictionary['namespaces']['webx-test']['card']['submit']);
        $this->assertSame('Sign out', $dictionary['namespaces']['webx-test']['nav']['sign-out']);
    }

    #[Test]
    public function a_half_translated_group_keeps_the_lines_it_has_and_borrows_the_rest(): void
    {
        // The alternative — a group falling back whole — means one new key in a release blanks
        // out a screen that was translated a year ago.
        $dictionary = $this->builder()->build('uk');
        $card = $dictionary['namespaces']['webx-test']['card'];

        $this->assertSame('Електронна пошта', $card['email']);
        $this->assertSame('Password', $card['password']);
        $this->assertSame(
            'Ці дані не підходять до жодного облікового запису.',
            $card['errors']['invalid'],
        );
        // Nested too: the untranslated line inside a translated group still arrives.
        $this->assertSame('Too many attempts. Try again in :seconds seconds.', $card['errors']['throttled']);
    }

    #[Test]
    public function only_the_panels_own_namespaces_are_sent_to_the_browser(): void
    {
        $dictionary = $this->builder()->build('en');

        $this->assertArrayHasKey('webx-test', $dictionary['namespaces']);
        $this->assertArrayNotHasKey('shop', $dictionary['namespaces']);
    }

    #[Test]
    public function what_the_application_published_over_a_package_wins(): void
    {
        // How a site translates a language the module never shipped, or corrects a word it
        // disagrees with, without touching anything in vendor.
        $published = $this->app->langPath('vendor/webx-test/uk');
        mkdir($published, 0o777, true);
        file_put_contents($published.'/card.php', "<?php\n\nreturn ['submit' => 'Увійти'];\n");

        try {
            $dictionary = $this->builder()->build('uk');

            $this->assertSame('Увійти', $dictionary['namespaces']['webx-test']['card']['submit']);
            // And the rest of the group is still there.
            $this->assertSame('Електронна пошта', $dictionary['namespaces']['webx-test']['card']['email']);
        } finally {
            unlink($published.'/card.php');
            rmdir($published);
        }
    }

    #[Test]
    public function a_group_only_the_application_has_is_found(): void
    {
        $published = $this->app->langPath('vendor/webx-test/en');
        mkdir($published, 0o777, true);
        file_put_contents($published.'/extra.php', "<?php\n\nreturn ['hello' => 'Hello'];\n");

        try {
            $dictionary = $this->builder()->build('en');

            $this->assertSame('Hello', $dictionary['namespaces']['webx-test']['extra']['hello']);
        } finally {
            unlink($published.'/extra.php');
            rmdir($published);
        }
    }
}
