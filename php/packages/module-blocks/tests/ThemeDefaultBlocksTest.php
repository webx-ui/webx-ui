<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Demo\BlocksDemo;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Themes\LayerSync;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Models\Form;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\Blocks\SchemaCompatibility;
use WebxUi\Themes\Sync\SyncLine;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The six blocks of `webx-ui/theme-default` (THEMES §9.1) on a site, through the real sync: they
 * publish — publishing renders each sample —, every variant prints, and the demo of this package
 * yields to them (§23, TH2.3): it does not seed a slug the theme carries, and a site that got the
 * demo first hands its untouched copy over only when asked with `--adopt`.
 */
final class ThemeDefaultBlocksTest extends TestCase
{
    private const string THEME = 'webx-ui/theme-default';

    private const array SIX = ['cards', 'contact', 'cta', 'hero', 'media-text', 'text'];

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The engine first: it wraps the translation loader, and a provider before it may build the translator with the plain one.
        return [
            ThemeServiceProvider::class,
            ...parent::getPackageProviders($app),
            WidgetsServiceProvider::class,
            SettingsServiceProvider::class,
            InboxServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-themes.theme', self::THEME);
        $app->usePublicPath(str_replace('\\', '/', sys_get_temp_dir()).'/webx-theme-default-blocks');
        $app->resolving(ThemeLocator::class, static function (ThemeLocator $locator): void {
            $locator->register(self::THEME, self::path());
        });
    }

    #[Test]
    public function the_six_blocks_are_put_on_the_site_and_published(): void
    {
        $lines = $this->sync();

        foreach (self::SIX as $slug) {
            $this->assertLine($slug, 'created from '.self::THEME, $lines);
        }

        $this->assertSame(self::SIX, Block::query()->whereNotNull('published_version_id')->orderBy('slug')->pluck('slug')->all());
        $this->assertSame([], array_values(array_filter($lines, static fn (SyncLine $line): bool => $line->refused)));
    }

    #[Test]
    public function every_variant_prints(): void
    {
        $this->sync();

        $html = $this->render([
            $this->node('hero', ['title' => 'Plain', 'button_label' => 'Go', 'button_url' => '/go', 'second_label' => 'Or', 'second_url' => '/or']),
            $this->node('hero', ['title' => 'On the accent', 'tone' => 'accent', 'align' => 'center', 'width' => 'bleed']),
            $this->node('hero', ['title' => 'Over a picture', 'tone' => 'image']),
            $this->node('media-text', ['heading' => 'Words alone', 'body' => '<p>No picture.</p>', 'side' => 'end', 'tone' => 'surface']),
            $this->node('cards', ['heading' => 'Four', 'columns' => '4', 'pictures' => 'icon', 'cards' => [
                ['title' => 'Linked', 'text' => 'A card', 'link_label' => 'More', 'link_url' => '/more'],
                ['title' => 'Not linked', 'text' => 'Another'],
                ['title' => '', 'text' => '', 'link_url' => ''],
            ]]),
            $this->node('cta', ['heading' => 'Ask', 'button_label' => 'Now', 'button_url' => '/now', 'tone' => 'plain']),
        ]);

        $this->assertStringContainsString('class="b-hero b-hero--plain"', $html);
        $this->assertStringContainsString('b-hero__button b-hero__button--quiet" href="/or"', $html);
        $this->assertStringContainsString('class="b-hero b-hero--accent b-hero--center site-bleed"', $html);
        $this->assertStringContainsString('class="b-hero b-hero--image"', $html);
        $this->assertStringContainsString('<div class="b-hero__shade"></div>', $html);
        $this->assertStringContainsString('b-media-text--end b-media-text--surface b-media-text--alone', $html);
        $this->assertStringContainsString('class="b-cards b-cards--4 b-cards--icon"', $html);
        $this->assertSame(2, substr_count($html, '<li class="b-cards__card'), 'a card with nothing in it is left out');
        $this->assertStringContainsString('<a class="b-cards__link" href="/more">Linked</a>', $html);
        $this->assertStringContainsString('class="b-cta b-cta--plain"', $html);

        // Nothing to say is nothing printed, not an empty frame.
        $this->assertSame('', trim($this->render([$this->node('hero'), $this->node('text'), $this->node('cta')])));
    }

    /** The demo `text` of this package and anything typed through MCP: plain lines, escaped. */
    #[Test]
    public function the_text_reads_a_body_written_as_plain_lines(): void
    {
        $this->sync();

        $plain = $this->render([$this->node('text', ['heading' => 'Why', 'body' => "One line\nAnd <b>another</b>"])]);
        $rich = $this->render([$this->node('text', ['body' => '<p>Rich <em>text</em></p>', 'width' => 'wide'])]);

        $this->assertStringContainsString('<div class="b-text__body"><p>One line</p><p>And &lt;b&gt;another&lt;/b&gt;</p></div>', $plain);
        $this->assertStringContainsString('class="b-text b-text--narrow"', $plain);
        $this->assertStringContainsString('<div class="b-text__body"><p>Rich <em>text</em></p></div>', $rich);
        $this->assertStringContainsString('class="b-text b-text--wide"', $rich);
    }

    #[Test]
    public function the_contact_block_prints_the_contacts_of_the_settings_and_a_form_of_the_inbox(): void
    {
        $this->sync();
        $this->app->make(Settings::class)->save([
            'contacts.phones' => [['number' => '+800 1234 5678', 'label' => null, 'primary' => true, 'messengers' => []]],
            'contacts.emails' => [['email' => 'hello@example.com', 'label' => null]],
            'contacts.addresses' => [['address' => ['en' => '1 Example Street, Sample City'], 'latitude' => null, 'longitude' => null, 'map' => null, 'primary' => true]],
        ]);
        $form = Form::query()->create(['slug' => 'contact', 'title' => ['en' => 'Contact'], 'is_enabled' => true, 'options' => []]);
        $form->fields()->create(['name' => 'email', 'type' => FieldType::Email, 'title' => ['en' => 'E-mail'], 'is_enabled' => true, 'is_required' => true, 'position' => 0]);

        $html = $this->render([$this->node('contact', ['heading' => 'Write to us', 'form' => 'contact', 'form_heading' => 'Or here'])]);

        $this->assertStringContainsString('class="b-contact b-contact--both"', $html);
        // The label after its icon of the theme's sprite (THEMES §13.1).
        $this->assertMatchesRegularExpression('#<h3 class="b-contact__label"><svg class="theme-icon b-contact__icon"[^>]*>.*?</svg>Phone</h3>#s', $html);
        $this->assertStringContainsString('href="tel:+800-1234-5678"', $html);
        $this->assertStringContainsString('href="mailto:hello@example.com"', $html);
        $this->assertStringContainsString('1 Example Street, Sample City', $html);
        $this->assertStringContainsString('<h3 class="b-contact__form-heading">Or here</h3>', $html);
        $this->assertMatchesRegularExpression('#<form[^>]+/webx/forms/contact#', $html);

        // Without the contacts and with a form nobody has: the heading, alone.
        $alone = $this->render([$this->node('contact', ['heading' => 'Alone', 'show_contacts' => false, 'form' => 'nobody'])]);
        $this->assertStringContainsString('<h2 class="b-contact__heading">Alone</h2>', $alone);
        $this->assertStringNotContainsString('b-contact__details', $alone);
        $this->assertStringNotContainsString('<form', $alone);
    }

    /** The demo's hero, seeded before the theme carried one, is what an adopted type reads. */
    #[Test]
    public function the_theme_hero_holds_every_field_of_the_demo_hero(): void
    {
        $demo = self::demo('hero');
        $theme = self::document('hero');

        $this->assertSame([], SchemaCompatibility::problems($demo['schema'], $theme['schema']));
        $this->assertSame(['field "body" was wx-textarea, is wx-rich-text'], SchemaCompatibility::problems(self::demo('text')['schema'], self::document('text')['schema']), 'read as plain lines, above');
    }

    #[Test]
    public function the_demo_does_not_seed_a_slug_the_theme_carries(): void
    {
        // Not synced yet: the demo says where the type comes from and leaves the slug free.
        $ledger = $this->demoSeeded();

        $this->assertFalse(Block::query()->where('slug', 'hero')->exists());
        $this->assertFalse(Block::query()->where('slug', 'text')->exists());
        $this->assertStringContainsString('The block type hero comes with the theme', implode(' ', $ledger->takeNotes()));

        // So the next sync is not refused, and `columns` takes the theme's text.
        $lines = $this->sync();
        $this->assertLine('hero', 'created from '.self::THEME, $lines);
        $this->assertLine('text', 'created from '.self::THEME, $lines);
        $this->assertSame(['text'], Block::query()->where('slug', 'columns')->firstOrFail()->allow);
    }

    #[Test]
    public function an_untouched_demo_type_is_refused_with_the_way_out_and_adopted_when_asked(): void
    {
        $hero = $this->oldDemo('hero');

        $refused = $this->line('hero', $this->sync());
        $this->assertTrue($refused->refused);
        $this->assertStringContainsString("It is the blocks demo's hero, unchanged: webx:theme:sync --adopt=hero hands it to the theme", $refused->detail);

        $this->assertLine('hero', 'would be adopted from '.self::THEME." (the blocks demo's, untouched)", $this->sync(dryRun: true, adopt: ['hero']));
        $this->assertNull($hero->fresh()?->theme_source);

        $this->artisan('webx:theme:sync', ['--adopt' => ['hero,text']])
            ->expectsOutputToContain('adopted from '.self::THEME)
            ->assertSuccessful();

        $hero = $hero->fresh();
        $this->assertSame(self::THEME, $hero?->theme_source);
        $this->assertSame(2, BlockVersion::query()->where('block_id', $hero->id)->count(), 'the demo version stays in the history');
        $this->assertLine('hero', 'unchanged', $this->sync());

        // A page written for the demo's hero prints in the theme's.
        $html = $this->render([$this->node('hero', ['title' => 'Old page', 'subtitle' => 'Its line', 'button_label' => 'Its button', 'button_url' => '/x'])]);
        $this->assertStringContainsString('<h1 class="b-hero__title">Old page</h1>', $html);
        $this->assertStringContainsString('<a class="b-hero__button" href="/x">Its button</a>', $html);
    }

    #[Test]
    public function a_demo_type_somebody_changed_is_not_adopted(): void
    {
        $hero = $this->oldDemo('hero');
        $hero->saveVersion(['styles' => ".b-hero {\n    padding: 1em;\n}\n"]);
        $hero->publish();

        $own = $this->line('hero', $this->sync());
        $this->assertTrue($own->refused);
        $this->assertStringContainsString('Rename or delete it in the panel', $own->detail);

        $asked = $this->line('hero', $this->sync(adopt: ['hero']));
        $this->assertTrue($asked->refused);
        $this->assertStringContainsString('not adopted — --adopt takes only the blocks demo\'s copy nobody has changed', $asked->detail);
        $this->assertNull($hero->fresh()?->theme_source);
    }

    /**
     * §10.3: a module's offer stands back from a slug the chain carries — on a new site the
     * theme's type is the one put there — and a type already on the site is still left alone.
     */
    #[Test]
    public function an_offer_yields_to_a_slug_the_theme_carries(): void
    {
        $offer = $this->offered('cta');
        $offers = $this->app->make(BlockOffers::class);

        $this->assertSame(self::THEME, $offers->carriedBy('cta'));
        $this->assertNull($offers->carriedBy('faq'));

        $this->artisan('webx:blocks:offered', ['--module' => ['tariffs']])
            ->expectsOutputToContain(self::THEME.' carries it')
            ->assertSuccessful();

        $this->assertSame(BlockOffers::CARRIED, $offers->install($offer));
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['tariffs']])->assertSuccessful();
        $this->assertSame(0, Block::query()->count(), 'nothing is installed from the offer');

        // The theme's type is the one sync puts there; afterwards the slug is simply taken.
        $this->assertLine('cta', 'created from '.self::THEME, $this->sync());
        $this->assertSame(BlockOffers::PRESENT, $offers->install($offer));
        $this->assertNotSame(BlockVersion::SOURCE_IMPORT, Block::query()->where('slug', 'cta')->firstOrFail()->publishedVersion?->source);
    }

    /**
     * The `cta` a module would offer: the same slug as the theme's, a markup of its own.
     *
     * @return array{module: string, slug: string, file: string, document: array<string, mixed>}
     */
    private function offered(string $slug): array
    {
        $directory = storage_path('framework/testing/offer-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($directory);
        $this->beforeApplicationDestroyed(static function () use ($directory): void {
            File::deleteDirectory($directory);
        });

        File::put($directory."/{$slug}.json", (string) json_encode([
            'slug' => $slug,
            'title' => 'Offered',
            'schema' => [['id' => 'title', 'type' => 'wx-input']],
            'template' => "<section data-wx-block=\"{$slug}\">{{ \$title }}</section>",
            'sample' => ['title' => 'Offered'],
        ]));

        $offers = $this->app->make(BlockOffers::class);
        $offers->offer('tariffs', $directory);

        return $offers->documents(['tariffs'])[0];
    }

    /** A demo type as the demo seeded it before the theme carried the slug. */
    private function oldDemo(string $slug): Block
    {
        $document = self::demo($slug);

        $block = new Block;
        $block->fill(BlockInput::values($document));
        $block->save();
        $block->saveVersion(BlockInput::content($document), BlockVersion::SOURCE_IMPORT, null, 'Demo content');
        $block->publish();

        return $block;
    }

    private function demoSeeded(): DemoLedger
    {
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('blocks');

        $this->app->make(BlocksDemo::class)->seed($ledger);

        return $ledger;
    }

    /**
     * @param  list<string>  $adopt
     * @return list<SyncLine>
     */
    private function sync(bool $dryRun = false, array $adopt = []): array
    {
        return $this->app->make(LayerSync::class)->sync($this->app->make(ThemeChain::class), false, $dryRun, $adopt);
    }

    /**
     * @param  list<SyncLine>  $lines
     */
    private function line(string $subject, array $lines): SyncLine
    {
        foreach ($lines as $line) {
            if ($line->subject === $subject) {
                return $line;
            }
        }

        $this->fail("No line for {$subject}: ".json_encode($lines));
    }

    /**
     * @param  list<SyncLine>  $lines
     */
    private function assertLine(string $subject, string $detail, array $lines): void
    {
        $line = $this->line($subject, $lines);

        $this->assertSame($detail, $line->detail);
        $this->assertFalse($line->refused, $line->detail);
    }

    /**
     * @return array{schema: list<mixed>}&array<string, mixed>
     */
    private static function document(string $slug): array
    {
        /** @var array{schema: list<mixed>}&array<string, mixed> */
        return json_decode((string) file_get_contents(self::path()."/blocks/{$slug}.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{schema: list<mixed>}&array<string, mixed>
     */
    private static function demo(string $slug): array
    {
        /** @var array{schema: list<mixed>}&array<string, mixed> */
        return json_decode((string) file_get_contents(dirname(__DIR__)."/resources/demo/{$slug}.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    private static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2)).'/theme-default';
    }
}
