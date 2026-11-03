<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Access\PermissionMatrix;
use WebxUi\Auth\Testing\AssertsPermissions;
use WebxUi\Delivery\Panel\DeliverySection;

/**
 * Nothing of the section is open to an administrator who holds no permission, and every
 * permission asked for is one a module declares (WEBX_UI_PERMISSIONS.md §15). The step of the
 * checkout (`/api/delivery/*`) is the buyer's and is not a panel route.
 */
final class AccessCoverageTest extends PanelTestCase
{
    use AssertsPermissions;

    /** The reading screens of the six entries. */
    private const array READS = ['methods', 'points', 'carriers', 'geography/countries', 'geography/localities', 'popular', 'bindings'];

    /** The roles' matrix names the section, not its first entry «Methods», in every language. */
    #[Test]
    public function the_roles_matrix_calls_the_section_by_its_name(): void
    {
        $this->signIn(['delivery.view']);
        $title = fn (): string => collect($this->app->make(PermissionMatrix::class)->for(auth('cms')->user()))->firstWhere('module', 'delivery')['title'];

        $this->assertSame('Delivery', $title());
        $this->app->setLocale('ru');
        $this->assertSame('Доставка', $title());
    }

    #[Test]
    public function every_route_asks_for_a_declared_permission(): void
    {
        $this->assertPanelRoutesNeedPermission(['WebxUi\\Delivery\\', 'api/cms/delivery/']);
    }

    #[Test]
    public function reading_does_not_open_the_writes(): void
    {
        $this->signIn(['delivery.view']);

        $this->getJson($this->api('methods'))->assertOk();
        $this->getJson($this->api('geography/countries'))->assertOk();
        $this->postJson($this->api('geography/countries'), ['code' => 'XX'])->assertForbidden();
        $this->putJson($this->api('methods/order'), ['ids' => []])->assertForbidden();
        $this->putJson($this->api('popular'), ['ids' => []])->assertForbidden();
        $this->postJson($this->api('bindings/batch'), ['action' => 'ignore', 'all' => true])->assertForbidden();
    }

    /**
     * A right of another section opens nothing here: neither an entry of the menu — the five after
     * the first declare no permissions of their own and were shown to everybody — nor a read, nor
     * a write.
     */
    #[Test]
    public function another_sections_right_opens_nothing_of_the_delivery(): void
    {
        $this->signIn(['payments.view']);

        $this->assertSame(array_fill_keys(array_keys(DeliverySection::SECTIONS), false), $this->entries());

        foreach (self::READS as $path) {
            $this->getJson($this->api($path))->assertForbidden();
        }

        $this->putJson($this->api('popular'), ['ids' => []])->assertForbidden();
        $this->putJson($this->api('methods/order'), ['ids' => []])->assertForbidden();
        $this->postJson($this->api('points'), [])->assertForbidden();
        $this->postJson($this->api('geography/countries'), ['code' => 'XX'])->assertForbidden();
    }

    #[Test]
    public function a_reader_sees_every_entry_and_reads_every_screen(): void
    {
        $this->signIn(['delivery.view']);

        $this->assertSame(array_fill_keys(array_keys(DeliverySection::SECTIONS), true), $this->entries());

        foreach (self::READS as $path) {
            $this->getJson($this->api($path))->assertOk();
        }
    }

    #[Test]
    public function the_waybills_right_alone_shows_no_settings(): void
    {
        $this->signIn(['delivery.ship']);

        $this->assertSame(array_fill_keys(array_keys(DeliverySection::SECTIONS), false), $this->entries());
        $this->getJson($this->api('points'))->assertForbidden();
    }

    #[Test]
    public function the_manager_writes(): void
    {
        $this->signIn(['delivery.manage']);

        $this->assertSame(array_fill_keys(array_keys(DeliverySection::SECTIONS), true), $this->entries());
        $this->getJson($this->api('points'))->assertOk();
        $this->putJson($this->api('popular'), ['ids' => []])->assertOk();
    }

    /** @return array<string, bool> the section's entries of the manifest: id → whether it opens */
    private function entries(): array
    {
        $modules = $this->getJson('/api/cms/manifest')->assertOk()->json('data.modules');
        $entries = [];

        foreach ($modules as $module) {
            if ($module['group'] === DeliverySection::GROUP) {
                $entries[$module['id']] = $module['allowed'];
            }
        }

        return $entries;
    }
}
