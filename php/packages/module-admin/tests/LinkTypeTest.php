<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\SiteUrls;
use WebxUi\Admin\Facades\Screens;
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

    /**
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    private function store(?array $value): ?array
    {
        /** @var array<string, mixed>|null $stored */
        $stored = $this->values()->validate('blocks.cta', [
            'cta.link' => $value,
        ], static fn (string $permission): bool => true)['cta.link'] ?? null;

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function resolve(array $stored, ?string $locale = null): array
    {
        /** @var array<string, mixed> $resolved */
        $resolved = $this->values()->resolveAll('blocks.cta', ['cta.link' => $stored], $locale)['cta.link'];

        return $resolved;
    }

    private function values(): ScreenValues
    {
        return $this->app->make(ScreenValues::class);
    }
}
