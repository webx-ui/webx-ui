<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\AssetDownloads;
use WebxUi\Admin\Contracts\SiteUrls;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Links\LinkAttributes;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Types\LinkType;
use WebxUi\Admin\Tests\Fixtures\FakeLinkSource;
use WebxUi\Admin\Tests\Fixtures\FakeSiteUrls;
use WebxUi\Localization\Locales;

/**
 * `wx-link` on the server: what may arrive, what is kept, and what the site reads back.
 */
final class LinkTypeTest extends TestCase
{
    /**
     * The library a file link asks, with one file in it — bound before the field types are built,
     * the way the media module binds it in `register()`.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(LinkSources::class)->register(new FakeLinkSource(type: 'page', candidates: [
            new LinkCandidate(1, 'About us', 'https://example.test/about', true),
            new LinkCandidate(2, 'Pricing', null, false),
        ]));

        Screens::register('blocks.cta', [
            ['id' => 'link', 'type' => 'wx-link', 'name' => 'cta.link'],
            ['id' => 'translated', 'type' => 'wx-link', 'name' => 'cta.translated', 'localized' => true],
            ['id' => 'file', 'type' => 'wx-link', 'name' => 'cta.file', 'props' => ['files' => true]],
        ]);
    }

    #[Test]
    public function an_entity_link_is_kept_as_the_morph_pair(): void
    {
        $stored = $this->store([
            'target' => 'entity',
            'entity_type' => 'page',
            'entity_id' => 1,
            'new_tab' => true,
            'rel' => ['nofollow'],
        ]);

        $this->assertSame([
            'target' => 'entity',
            'entity_type' => 'page',
            'entity_id' => 1,
            'url' => null,
            'hash' => null,
            'new_tab' => true,
            'rel' => ['nofollow'],
        ], $stored);
    }

    /** The case the anchor was added for: a chosen page, and a place on it. */
    #[Test]
    public function an_anchor_is_appended_to_the_address_of_a_chosen_page(): void
    {
        $stored = $this->store([
            'target' => 'entity',
            'entity_type' => 'page',
            'entity_id' => 1,
            'hash' => '#team',
        ]);

        $this->assertSame('team', $stored['hash']);

        $resolved = $this->resolve($stored);

        $this->assertSame('https://example.test/about#team', $resolved['url']);
    }

    #[Test]
    public function an_anchor_is_appended_to_a_typed_address_too(): void
    {
        $this->app->instance(SiteUrls::class, new FakeSiteUrls);

        $stored = $this->store(['target' => 'url', 'url' => '/account#billing']);

        $this->assertSame('/account', $stored['url']);
        $this->assertSame('billing', $stored['hash']);
        $this->assertSame('https://example.test/uk/account#billing', $this->resolve($stored, 'uk')['url']);
    }

    /** An anchor with a space in it is a link that silently goes to the top of the page. */
    #[Test]
    public function an_anchor_that_is_not_a_name_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->store(['target' => 'url', 'url' => '/a', 'hash' => 'two words']);
    }

    #[Test]
    public function a_cleared_field_keeps_nothing(): void
    {
        $this->assertNull($this->store(['target' => 'none']));
        $this->assertNull($this->store(['target' => 'url', 'url' => '']));
        $this->assertNull($this->store(null));
    }

    #[Test]
    public function a_type_nobody_registered_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->store(['target' => 'entity', 'entity_type' => 'product', 'entity_id' => 4]);
    }

    #[Test]
    public function a_scheme_that_executes_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->store(['target' => 'url', 'url' => 'javascript:alert(1)']);
    }

    #[Test]
    public function an_address_longer_than_the_column_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->store(['target' => 'url', 'url' => '/'.str_repeat('a', 1024)]);
    }

    /** A link is the same in every language; a language map here would be three forgotten copies. */
    #[Test]
    public function a_localized_link_field_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->values()->validate('blocks.cta', [
            'cta.translated' => ['en' => ['target' => 'url', 'url' => '/a']],
        ], static fn (string $permission): bool => true);
    }

    #[Test]
    public function the_site_reads_an_entity_link_with_the_address_worked_out(): void
    {
        $resolved = $this->resolve(['target' => 'entity', 'entity_type' => 'page', 'entity_id' => 1]);

        $this->assertSame('https://example.test/about', $resolved['url']);
        $this->assertSame('About us', $resolved['label']);
        $this->assertTrue($resolved['available']);
    }

    /** The one thing a template has to check before printing a link to a draft. */
    #[Test]
    public function an_entity_that_is_not_on_the_site_says_so(): void
    {
        $resolved = $this->resolve(['target' => 'entity', 'entity_type' => 'page', 'entity_id' => 2]);

        $this->assertNull($resolved['url']);
        $this->assertFalse($resolved['available']);
    }

    #[Test]
    public function an_entity_that_is_gone_resolves_to_nothing_to_print(): void
    {
        $resolved = $this->resolve(['target' => 'entity', 'entity_type' => 'page', 'entity_id' => 999]);

        $this->assertNull($resolved['url']);
        $this->assertNull($resolved['label']);
        $this->assertFalse($resolved['available']);
    }

    #[Test]
    public function a_path_is_read_back_with_the_language_prefix(): void
    {
        $this->app->instance(SiteUrls::class, new FakeSiteUrls);

        $resolved = $this->resolve(['target' => 'url', 'url' => '/account', 'new_tab' => true], 'uk');

        $this->assertSame('https://example.test/uk/account', $resolved['url']);
        $this->assertSame('noopener noreferrer', $resolved['rel']);
        $this->assertTrue($resolved['available']);
    }

    /**
     * No address registry installed: the path is handed on as it was written.
     *
     * Built by hand rather than resolved, because the development root of this monorepo has
     * `webx-ui/routing` in it — and the case being checked is the panel that does not.
     */
    #[Test]
    public function without_a_registry_a_path_travels_as_written(): void
    {
        $sources = $this->app->make(LinkSources::class);
        $type = new LinkType($sources, new LinkUrls($sources, $this->app->make(Locales::class)));

        /** @var array<string, mixed> $resolved */
        $resolved = $type->resolve(['target' => 'url', 'url' => '/account'], []);

        $this->assertSame('/account', $resolved['url']);
    }

    #[Test]
    public function a_stored_scheme_that_executes_is_never_printed(): void
    {
        // Written around the rules — an old value, a script, a column edited by hand.
        $resolved = $this->resolve(['target' => 'url', 'url' => 'javascript:alert(1)']);

        $this->assertNull($resolved['url']);
    }

    /**
     * A file of the library (webx-ui#132): its key and the download switch are kept, and only a
     * file link carries them — every other link keeps the seven keys a menu item has columns for.
     */
    #[Test]
    public function a_file_link_is_kept_as_its_key_and_the_download_switch(): void
    {
        $stored = $this->store(['target' => 'file', 'path' => 'media/ab/cd/f00d.pdf', 'download' => true, 'hash' => 'page=2', 'new_tab' => true], 'cta.file');

        $this->assertSame([
            'target' => 'file',
            'entity_type' => null,
            'entity_id' => null,
            'url' => null,
            'hash' => null,
            'new_tab' => true,
            'rel' => [],
            'path' => 'media/ab/cd/f00d.pdf',
            'download' => true,
        ], $stored);

        // A path and a download flag sent with an address are not kept with it.
        $this->assertSame(['target', 'entity_type', 'entity_id', 'url', 'hash', 'new_tab', 'rel'], array_keys((array) $this->store(['target' => 'url', 'url' => '/a', 'path' => 'media/x.pdf', 'download' => true], 'cta.file')));
        $this->assertNull($this->store(['target' => 'file', 'path' => ''], 'cta.file'), 'an unfinished choice keeps nothing');
    }

    /** The menu, a redirect after a form — every caller of the rules with no node — has no file target. */
    #[Test]
    public function a_file_link_is_refused_where_the_field_did_not_ask_for_files(): void
    {
        try {
            $this->store(['target' => 'file', 'path' => 'media/ab/cd/f00d.pdf']);
            $this->fail('A field without `files` took a file.');
        } catch (ValidationException $refused) {
            $this->assertSame(['This field does not link to files.'], $refused->errors()['cta.link']);
        }

        $rules = $this->app->make(LinkType::class)->rules([]);
        $validator = validator(['link' => ['target' => 'file', 'path' => 'media/ab/cd/f00d.pdf']], ['link' => $rules]);

        $this->assertTrue($validator->fails());
    }

    #[Test]
    public function a_download_switch_that_is_not_one_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->store(['target' => 'file', 'path' => 'media/ab/cd/f00d.pdf', 'download' => 'yes please'], 'cta.file');
    }

    /** A key typed rather than picked — an agent guessing — is refused when it is new, not when it was held. */
    #[Test]
    public function a_new_file_key_the_library_does_not_have_is_refused(): void
    {
        $gone = ['target' => 'file', 'path' => 'media/no/ne/gone.pdf'];

        try {
            $this->values()->validate('blocks.cta', ['cta.file' => $gone], static fn (string $permission): bool => true, []);
            $this->fail('An unknown key was taken.');
        } catch (ValidationException $refused) {
            $this->assertSame(['There is no file media/no/ne/gone.pdf in the media library.'], $refused->errors()['cta.file']);
        }

        // Held already — a brochure deleted from the library — the block still saves.
        $kept = $this->values()->validate('blocks.cta', ['cta.file' => $gone], static fn (string $permission): bool => true, ['cta.file' => $gone]);

        $this->assertSame('media/no/ne/gone.pdf', $kept['cta.file']['path'] ?? null);
    }

    #[Test]
    public function the_site_reads_a_file_link_with_its_address_and_its_own_name(): void
    {
        $resolved = $this->resolve(['target' => 'file', 'path' => 'media/ab/cd/f00d.pdf', 'download' => true], null, 'cta.file');

        $this->assertSame('/storage/media/ab/cd/f00d.pdf', $resolved['url']);
        $this->assertSame('Price list.pdf', $resolved['download']);
        $this->assertSame('Price list.pdf', $resolved['label']);
        $this->assertTrue($resolved['available']);

        $opened = $this->resolve(['target' => 'file', 'path' => 'media/ab/cd/f00d.pdf'], null, 'cta.file');
        $this->assertNull($opened['download'], 'without the switch the file opens');

        $gone = $this->resolve(['target' => 'file', 'path' => 'media/no/ne/gone.pdf', 'download' => true], null, 'cta.file');
        $this->assertNull($gone['url']);
        $this->assertNull($gone['download']);
        $this->assertFalse($gone['available']);
    }

    #[Test]
    public function the_attributes_of_an_anchor_are_worked_out_once(): void
    {
        $this->assertSame(
            'href="https://example.com/a" target="_blank" rel="nofollow noopener noreferrer"',
            (string) LinkAttributes::of($this->resolve(['target' => 'url', 'url' => 'https://example.com/a', 'new_tab' => true, 'rel' => ['nofollow']])),
        );
        $this->assertSame(
            'href="/storage/media/ab/cd/f00d.pdf" download="Price list.pdf"',
            (string) LinkAttributes::of($this->resolve(['target' => 'file', 'path' => 'media/ab/cd/f00d.pdf', 'download' => true], null, 'cta.file')),
        );

        // A draft is not printed, and a chosen link that goes nowhere does not fall back.
        $this->assertNull(LinkAttributes::of($this->resolve(['target' => 'entity', 'entity_type' => 'page', 'entity_id' => 2]), '/old'));

        // The old field, while the link is empty: as it was, whatever the scheme — but never script.
        $this->assertSame('href="viber://chat?number=0000"', (string) LinkAttributes::of(null, ' viber://chat?number=0000 '));
        $this->assertSame('href="/a?x=1&amp;y=&quot;2&quot;"', (string) LinkAttributes::of(null, '/a?x=1&y="2"'));
        $this->assertNull(LinkAttributes::of(null, 'javascript:alert(1)'));
        $this->assertNull(LinkAttributes::of(null, ''));
        $this->assertNull(LinkAttributes::of(null, null));
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    private function store(?array $value, string $field = 'cta.link'): ?array
    {
        /** @var array<string, mixed>|null $stored */
        $stored = $this->values()->validate('blocks.cta', [
            $field => $value,
        ], static fn (string $permission): bool => true)[$field] ?? null;

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function resolve(array $stored, ?string $locale = null, string $field = 'cta.link'): array
    {
        /** @var array<string, mixed> $resolved */
        $resolved = $this->values()->resolveAll('blocks.cta', [$field => $stored], $locale)[$field];

        return $resolved;
    }

    private function values(): ScreenValues
    {
        return $this->app->make(ScreenValues::class);
    }
}
