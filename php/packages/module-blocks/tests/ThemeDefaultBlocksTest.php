<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\AssetDownloads;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Demo\BlocksDemo;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Panel\Importer;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Themes\LayerSource;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Models\Form;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\Blocks\SchemaCompatibility;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\ThemeReveal;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The blocks of `webx-ui/theme-default` (THEMES §9.1) on a site, through the real import
 * (`webx:blocks:import --theme`, what `webx:setup` prints for a dev copy): they publish — publishing renders each
 * sample —, every variant prints, and the demo of this package yields to them (§23, TH2.3): it
 * does not seed a slug the theme carries, and a site that got the demo first keeps its copy until
 * someone names it with `--replace`.
 */
final class ThemeDefaultBlocksTest extends TestCase
{
    private const string THEME = 'webx-ui/theme-default';

    private const array BLOCKS = ['cards', 'contact', 'cta', 'hero', 'media-text', 'text', 'text-tiles'];

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

        // The library a file link asks, with one file in it — bound before the field types are
        // built, the way the media module binds it in `register()`.
        $app->instance(AssetDownloads::class, new class implements AssetDownloads
        {
            public function downloads(array $paths): array
            {
                return array_combine($paths, array_map(static fn (string $path): ?array => $path === 'media/ab/cd/f00d.pdf'
                    ? ['url' => '/storage/media/ab/cd/f00d.pdf', 'name' => 'Price list.pdf']
                    : null, $paths));
            }
        });
    }

    #[Test]
    public function the_blocks_are_put_on_the_site_and_published(): void
    {
        $lines = $this->sync();

        foreach (self::BLOCKS as $slug) {
            $this->assertLine($slug, Importer::CREATED, $lines);
        }

        $this->assertSame(self::BLOCKS, Block::query()->whereNotNull('published_version_id')->orderBy('slug')->pluck('slug')->all());
        $this->assertSame([], array_values(array_filter($lines, static fn (array $row): bool => $row['error'] !== null)));
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
            $this->node('text-tiles', ['heading' => 'Tiles', 'shade' => 'light', 'width' => 'column', 'tiles' => [
                ['kind' => 'value', 'value' => '120', 'unit' => 'm²', 'label' => 'Space'],
                ['kind' => 'icon', 'label' => 'No icon yet'],
                ['kind' => 'value', 'value' => '', 'label' => ''],
            ]]),
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
        $this->assertStringContainsString('class="b-text-tiles b-text-tiles--light b-text-tiles--2 b-text-tiles--words"', $html);
        $this->assertStringContainsString('<p class="b-text-tiles__value">120 <span class="b-text-tiles__unit">m²</span></p>', $html);
        $this->assertSame(2, substr_count($html, '<li class="b-text-tiles__tile'), 'a tile with nothing in it is left out');

        // Nothing to say is nothing printed, not an empty frame.
        $this->assertSame('', trim($this->render([$this->node('hero'), $this->node('text'), $this->node('cta'), $this->node('text-tiles')])));
    }

    /**
     * The entrance on scroll (THEMES §9.3) through the types imported into the tables, not the
     * files: what the page, the panel's preview and `blocks_render` print. Off — no mark and no
     * directive left as text; on — the marks, and still no directive as text.
     */
    #[Test]
    public function the_imported_blocks_mark_their_entrance_only_when_the_theme_says_so(): void
    {
        $this->sync();
        $page = fn (): string => $this->render([
            $this->node('hero', ['title' => 'Hero']),
            $this->node('text', ['heading' => 'Text', 'body' => '<p>Words.</p>']),
            $this->node('media-text', ['heading' => 'Media', 'body' => '<p>Words.</p>']),
            $this->node('cards', ['heading' => 'Cards', 'cards' => [['title' => 'One', 'text' => 'A card']]]),
            $this->node('cta', ['heading' => 'Ask', 'button_label' => 'Now', 'button_url' => '/now']),
            $this->node('contact', ['heading' => 'Write', 'show_contacts' => false, 'form' => '']),
            $this->node('text-tiles', ['heading' => 'Tiles', 'tiles' => [['kind' => 'value', 'value' => '1', 'label' => 'One']]]),
        ]);

        $off = $page();
        $this->assertStringContainsString('class="b-cta b-cta--accent"', $off);
        $this->assertStringNotContainsString('data-webx-reveal', $off);
        $this->assertStringNotContainsStringIgnoringCase('@webxReveal', $off);

        $chain = new ThemeChain([new ThemeManifest('theme', '/theme', true, reveal: 'fade'), ...$this->app->make(ThemeChain::class)->layers]);
        $this->app->instance(ThemeReveal::class, new ThemeReveal($chain));
        $this->app->make(Renderer::class)->flush();

        $on = $page();
        $this->assertStringNotContainsStringIgnoringCase('@webxReveal', $on);
        $this->assertStringNotContainsString('@webx', $on);
        foreach (['b-hero__inner', 'b-text__inner', 'b-cards__head', 'b-cta__inner', 'b-contact__head', 'b-text-tiles__words'] as $class) {
            $this->assertMatchesRegularExpression('~class="'.$class.'[^"]*"[^>]*?\sdata-webx-reveal="fade"~', $on, $class);
        }
        foreach (['b-media-text__grid', 'b-cards__grid', 'b-contact__grid', 'b-text-tiles__tiles'] as $class) {
            $this->assertMatchesRegularExpression('~class="'.$class.'[^"]*"[^>]*?\sdata-webx-reveal="stagger" data-webx-reveal-effect="fade"~', $on, $class);
        }
    }

    /**
     * The buttons on `wx-link` (webx-ui#132): a new tab with its `rel`, a file of the library that
     * downloads under the name it was uploaded with, and the old `*_url` read while the link is
     * empty — a block saved before the field existed prints what it always printed.
     */
    #[Test]
    public function the_buttons_take_a_link_a_new_tab_and_a_download(): void
    {
        $this->sync();

        $file = ['target' => 'file', 'path' => 'media/ab/cd/f00d.pdf', 'download' => true];
        $away = ['target' => 'url', 'url' => 'https://example.com/plan', 'new_tab' => true, 'rel' => ['nofollow']];

        $html = $this->render([
            $this->node('hero', ['title' => 'Both', 'button_label' => 'Price list', 'button_link' => $file, 'second_label' => 'The plan', 'second_link' => $away]),
            $this->node('cta', ['heading' => 'Ask', 'button_label' => 'Now', 'button_link' => $away, 'button_url' => '/ignored']),
            $this->node('media-text', ['heading' => 'Words', 'button_label' => 'Read', 'button_link' => ['target' => 'file', 'path' => 'media/ab/cd/f00d.pdf']]),
            $this->node('cards', ['cards' => [['title' => 'Card', 'link' => $away]]]),
            $this->node('text-tiles', ['heading' => 'Tiles', 'button_label' => 'Brochure', 'button_link' => $file]),
        ]);

        $this->assertStringContainsString('<a class="b-hero__button" href="/storage/media/ab/cd/f00d.pdf" download="Price list.pdf">Price list</a>', $html);
        $this->assertStringContainsString('<a class="b-hero__button b-hero__button--quiet" href="https://example.com/plan" target="_blank" rel="nofollow noopener noreferrer">The plan</a>', $html);
        // A chosen link wins over the old address beside it.
        $this->assertStringContainsString('<a class="b-cta__button" href="https://example.com/plan" target="_blank" rel="nofollow noopener noreferrer">Now</a>', $html);
        $this->assertStringNotContainsString('/ignored', $html);
        // A file without the switch opens rather than downloads.
        $this->assertStringContainsString('<a class="b-media-text__button" href="/storage/media/ab/cd/f00d.pdf">Read</a>', $html);
        $this->assertStringContainsString('<a class="b-cards__link" href="https://example.com/plan" target="_blank" rel="nofollow noopener noreferrer">Card</a>', $html);
        $this->assertStringContainsString('<a class="b-text-tiles__button" href="/storage/media/ab/cd/f00d.pdf" download="Price list.pdf">Brochure</a>', $html);

        // A file the library no longer has, and a link with nowhere to go: no button, and no old
        // address put back in its place.
        $gone = $this->render([$this->node('hero', ['title' => 'Gone', 'button_label' => 'Price list', 'button_link' => ['target' => 'file', 'path' => 'media/no/ne/gone.pdf', 'download' => true], 'button_url' => '/old'])]);
        $this->assertStringNotContainsString('b-hero__button', $gone);

        // The old shape, as every site saved it: the same anchors as before the link fields.
        $old = $this->render([
            $this->node('hero', ['title' => 'Old', 'button_label' => 'Go', 'button_url' => '/go', 'second_label' => 'Chat', 'second_url' => 'viber://chat?number=0000']),
            $this->node('cta', ['heading' => 'Old', 'button_label' => 'Now', 'button_url' => '/now']),
            $this->node('media-text', ['heading' => 'Old', 'button_label' => 'Read', 'button_url' => '/read']),
            $this->node('cards', ['cards' => [['title' => 'Old card', 'link_url' => '/card']]]),
        ]);

        $this->assertStringContainsString('<a class="b-hero__button" href="/go">Go</a>', $old);
        $this->assertStringContainsString('<a class="b-hero__button b-hero__button--quiet" href="viber://chat?number=0000">Chat</a>', $old);
        $this->assertStringContainsString('<a class="b-cta__button" href="/now">Now</a>', $old);
        $this->assertStringContainsString('<a class="b-media-text__button" href="/read">Read</a>', $old);
        $this->assertStringContainsString('<a class="b-cards__link" href="/card">Old card</a>', $old);
    }

    /**
     * Why the buttons got new fields beside the old ones rather than a new type for the old ids: a
     * site theme that overrides one of these blocks with its own `button_url` as `wx-input` — the
     * only shape there was — must stay a compatible override (rule 16), and its template printing
     * `{{ $button_url }}` must keep being handed a string.
     */
    #[Test]
    public function an_override_written_for_the_old_buttons_stays_compatible(): void
    {
        $old = [
            'hero' => ['button_url', 'second_url'],
            'cta' => ['button_url', 'second_url'],
            'media-text' => ['button_url'],
        ];

        foreach ($old as $slug => $fields) {
            $override = array_map(static fn (string $id): array => ['id' => $id, 'type' => 'wx-input', 'label' => $id], $fields);
            $schema = SchemaCompatibility::inherit(self::document($slug)['schema'], $override)['schema'];

            $this->assertSame([], SchemaCompatibility::problems(self::document($slug)['schema'], $schema), $slug);
        }

        $cards = [['id' => 'cards', 'type' => 'wx-repeater', 'label' => 'Cards', 'children' => [['id' => 'link_url', 'type' => 'wx-input', 'label' => 'Link']]]];
        $schema = SchemaCompatibility::inherit(self::document('cards')['schema'], $cards)['schema'];

        $this->assertSame([], SchemaCompatibility::problems(self::document('cards')['schema'], $schema));

        // In the panel an old address shows only while it holds one. The `default` is what makes a
        // block saved after the change — no key at all — read as empty: the form fills defaults in
        // before it asks a `visible` condition, and the condition cannot name a missing key.
        $old = [
            ['hero', self::document('hero')['schema'], ['button_url', 'second_url']],
            ['cta', self::document('cta')['schema'], ['button_url', 'second_url']],
            ['media-text', self::document('media-text')['schema'], ['button_url']],
            ['cards', self::field(self::document('cards')['schema'], 'cards')['children'] ?? [], ['link_url']],
        ];

        foreach ($old as [$slug, $fields, $ids]) {
            foreach ($ids as $id) {
                $field = self::field($fields, $id);

                $this->assertSame('wx-input', $field['type'] ?? null, "{$slug}.{$id}");
                $this->assertSame('', $field['default'] ?? null, "{$slug}.{$id}");
                $this->assertSame(['all' => [['when' => $id, 'not' => null], ['when' => $id, 'not' => '']]], $field['visible'] ?? null, "{$slug}.{$id}");
            }
        }
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

    /** The form's description is the line under its title in a dialog: a block with headings of its own does not repeat it. */
    #[Test]
    public function the_contact_block_prints_the_forms_description_by_its_switch(): void
    {
        $this->sync();
        $form = Form::query()->create(['slug' => 'contact', 'title' => ['en' => 'Contact'], 'is_enabled' => true, 'options' => ['description' => ['en' => 'We answer within a day.']]]);
        $form->fields()->create(['name' => 'email', 'type' => FieldType::Email, 'title' => ['en' => 'E-mail'], 'is_enabled' => true, 'is_required' => true, 'position' => 0]);
        $line = '<p class="webx-inbox-form__description">We answer within a day.</p>';

        // Off by default — a block saved before the switch reads its default too.
        $this->assertStringNotContainsString($line, $this->render([$this->node('contact', ['heading' => 'Write to us', 'show_contacts' => false, 'form' => 'contact'])]));
        $this->assertStringNotContainsString($line, $this->render([$this->node('contact', ['show_contacts' => false, 'form' => 'contact', 'show_form_description' => false])]));

        // On: the line under the form, as in its dialog.
        $this->assertStringContainsString($line, $this->render([$this->node('contact', ['heading' => 'Write to us', 'show_contacts' => false, 'form' => 'contact', 'show_form_description' => true])]));
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

        // So the import creates the theme's, and `columns` takes the theme's text.
        $lines = $this->sync();
        $this->assertLine('hero', Importer::CREATED, $lines);
        $this->assertLine('text', Importer::CREATED, $lines);
        $this->assertSame(['text'], Block::query()->where('slug', 'columns')->firstOrFail()->allow);
    }

    /** A site that got the demo first keeps its hero — it is the site's now — until someone names it. */
    #[Test]
    public function a_demo_type_seeded_before_differs_and_is_replaced_only_by_name(): void
    {
        $hero = $this->oldDemo('hero');

        $this->assertLine('hero', Importer::DIFFERS, $this->sync());
        $this->assertNull($hero->fresh()?->theme_source);
        $this->assertSame(1, BlockVersion::query()->where('block_id', $hero->id)->count());

        $this->artisan('webx:blocks:import', ['--theme' => true, '--replace' => ['hero'], '--publish' => true])
            ->expectsOutputToContain('replaced · v1 → v2 · published v2')
            ->assertSuccessful();

        $hero = $hero->fresh();
        $this->assertSame(self::THEME, $hero?->theme_source);
        $this->assertSame(2, BlockVersion::query()->where('block_id', $hero->id)->count(), 'the demo version stays in the history');
        $this->assertLine('hero', Importer::UNCHANGED, $this->sync());

        // A page written for the demo's hero prints in the theme's.
        $html = $this->render([$this->node('hero', ['title' => 'Old page', 'subtitle' => 'Its line', 'button_label' => 'Its button', 'button_url' => '/x'])]);
        $this->assertStringContainsString('<h1 class="b-hero__title">Old page</h1>', $html);
        $this->assertStringContainsString('<a class="b-hero__button" href="/x">Its button</a>', $html);
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
            ->expectsOutputToContain(self::THEME.' carries it — webx:blocks:import --theme puts that one on the site')
            ->assertSuccessful();

        $this->assertSame(BlockOffers::CARRIED, $offers->install($offer));
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['tariffs']])->assertSuccessful();
        $this->assertSame(0, Block::query()->count(), 'nothing is installed from the offer');

        // The theme's type is the one the import puts there; afterwards the slug is simply taken.
        $this->assertLine('cta', Importer::CREATED, $this->sync());
        $this->assertSame(BlockOffers::PRESENT, $offers->install($offer));
        $this->assertSame(self::THEME, Block::query()->where('slug', 'cta')->value('theme_source'));
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
     * The theme's types through the import `webx:setup` prints, by slug.
     *
     * @return array<string, array{status: string, error: string|null}>
     */
    private function sync(): array
    {
        $rows = $this->app->make(Importer::class)->import(LayerSource::read($this->app->make(ThemeChain::class)), publish: true);

        return array_column($rows, null, 'slug');
    }

    /**
     * @param  array<string, array{status: string, error: string|null}>  $rows
     */
    private function assertLine(string $slug, string $status, array $rows): void
    {
        $this->assertArrayHasKey($slug, $rows);
        $this->assertNull($rows[$slug]['error']);
        $this->assertSame($status, $rows[$slug]['status']);
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

    /**
     * The field of a schema by its id, at the top level.
     *
     * @param  array<mixed>  $nodes
     * @return array<string, mixed>|null
     */
    private static function field(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (is_array($node) && ($node['id'] ?? null) === $id) {
                /** @var array<string, mixed> $node */
                return $node;
            }
        }

        return null;
    }

    private static function path(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2)).'/theme-default';
    }
}
