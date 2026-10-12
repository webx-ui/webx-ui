<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Mail\SubmissionReceived;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Themes\ThemeServiceProvider;

/**
 * The letter's frame and colours (THEMES §7.5): the theme's `mail.layout` and the values of its
 * tokens on a site with a theme; this package's frame and theme-default's values without one.
 */
final class LetterLookTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ThemeServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.name', 'Harbor Logistics');
    }

    /**
     * @param  Application  $app
     */
    protected function withTheme($app): void
    {
        $app['config']->set('webx-themes.theme', str_replace('\\', '/', __DIR__).'/Fixtures/mail-theme');
    }

    #[Test]
    public function without_a_theme_the_letter_stands_in_its_own_frame_in_the_default_colours(): void
    {
        $html = $this->letter();

        $this->assertStringContainsString('Harbor Logistics</a>', $html);
        $this->assertStringContainsString('background-color:#1f5fbf;color:#ffffff;', $html);
        $this->assertStringContainsString('border-bottom:1px solid #dde1e6;', $html);
        $this->assertStringNotContainsString('var(', $html);
        $this->assertStringNotContainsString('data-frame', $html);
    }

    #[Test]
    #[DefineEnvironment('withTheme')]
    public function under_a_theme_it_stands_in_the_themes_frame_and_takes_its_colours(): void
    {
        $html = $this->letter();

        $this->assertStringContainsString('<div data-frame="theme" style="background-color:#fffdf9;color:#2a211b;">Contact|', $html);
        $this->assertStringContainsString('background-color:#a4461f;color:#ffffff;', $html);
        $this->assertStringContainsString('border-radius:2px;', $html);
        $this->assertStringContainsString('color:#655a50;', $html);
        $this->assertStringNotContainsString('var(', $html);
    }

    /** The preview the kitchen sink shows: the letter of answers nobody sent, nothing saved. */
    #[Test]
    public function a_sample_is_the_letter_of_answers_nobody_sent(): void
    {
        $form = $this->form('contact');

        $html = SubmissionReceived::sample($form, ['name' => 'Ada', 'message' => "Two\nlines", 'nothing' => 'here'])->render();

        $this->assertStringContainsString('Ada', $html);
        $this->assertStringContainsString("Two<br />\nlines", $html);
        $this->assertStringNotContainsString('here', $html);
        $this->assertSame(0, Submission::query()->count());
    }

    private function letter(): string
    {
        $this->form('contact', [], ['recipients' => [['email' => 'sales@example.test']]]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello'],
        ])->assertOk();

        return (new SubmissionReceived(Submission::query()->sole()))->render();
    }
}
