<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Settings\Contacts\Contacts;
use WebxUi\Settings\Contacts\ContactSections;
use WebxUi\Settings\Settings;

/**
 * `webx-settings.contacts.sections`: the parts of the Contacts tab a site does not use are hidden
 * from the form and nothing else — the keys stay writable, `contacts()` reads them, and a site's
 * patch that removed a card before this existed still applies.
 */
final class ContactSectionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function by_default_only_phones_emails_and_addresses_are_drawn(): void
    {
        $tree = app(ScreenRegistry::class)->tree(Settings::SCREEN);

        $this->assertFalse($this->visible($tree, 'contacts-hours-card'));
        $this->assertFalse($this->visible($tree, 'contacts-channels-card'));
        $this->assertTrue($this->visible($tree, 'contacts-phones-card'));
        $this->assertTrue($this->visible($tree, 'contacts-emails'));
        $this->assertTrue($this->visible($tree, 'contacts-addresses-card'));

        // Hidden, not cut out: every key is still a field the write side accepts.
        $names = array_column(app(ScreenRegistry::class)->fields(Settings::SCREEN), 'name');

        foreach (Contacts::KEYS as $key) {
            $this->assertContains($key, $names);
        }
    }

    #[Test]
    public function a_section_left_out_of_a_card_hides_only_its_own_fields(): void
    {
        $tree = $this->built(['phones', 'addresses', 'socials']);

        $this->assertTrue($this->visible($tree, 'contacts-phones-card'));
        $this->assertFalse($this->visible($tree, 'contacts-emails'));
        $this->assertTrue($this->visible($tree, 'contacts-channels-card'));
        $this->assertFalse($this->visible($tree, 'contacts-messengers'));
        $this->assertTrue($this->visible($tree, 'contacts-socials'));
        $this->assertFalse($this->visible($tree, 'contacts-hours-card'));
    }

    #[Test]
    public function every_section_draws_the_whole_tab_and_none_hides_it(): void
    {
        $all = $this->built(array_keys(ContactSections::NODES));

        foreach (array_keys(ContactSections::CARDS) as $card) {
            $this->assertTrue($this->visible($all, $card));
        }

        $this->assertFalse($this->visible($this->built([]), 'contacts'));
    }

    #[Test]
    public function a_site_patch_that_removed_a_card_still_applies(): void
    {
        $tree = $this->built(ContactSections::DEFAULT, [
            ['op' => 'remove', 'target' => 'contacts-hours-card'],
            ['op' => 'remove', 'target' => 'contacts-channels-card'],
        ]);

        $this->assertNull(Tree::find($tree, 'contacts-hours-card'));
        $this->assertNull(Tree::find($tree, 'contacts-channels-card'));
        $this->assertTrue($this->visible($tree, 'contacts-phones-card'));
    }

    #[Test]
    public function a_misspelt_section_is_refused_by_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown section(s) hour');

        ContactSections::patch(['phones', 'hour']);
    }

    #[Test]
    public function a_config_without_the_key_gets_the_default(): void
    {
        $this->assertSame(ContactSections::DEFAULT, ContactSections::configured(null));
        $this->assertSame(['hours'], ContactSections::configured(['hours']));
    }

    /**
     * The reference screen with the sections' patch and then the site's, as the providers lay them.
     *
     * @param  list<string>  $sections
     * @param  list<array<string, mixed>>  $site
     * @return list<array<string, mixed>>
     */
    private function built(array $sections, array $site = []): array
    {
        $registry = new ScreenRegistry;
        $registry->register(Settings::SCREEN, __DIR__.'/../resources/screens/index.json');
        $registry->extend(Settings::SCREEN, ContactSections::patch($sections));

        if ($site !== []) {
            $registry->extend(Settings::SCREEN, $site);
        }

        return $registry->tree(Settings::SCREEN);
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     */
    private function visible(array $tree, string $id): bool
    {
        $node = Tree::find($tree, $id);
        $this->assertNotNull($node, "no node {$id}");

        return ($node['visible'] ?? true) !== false;
    }
}
