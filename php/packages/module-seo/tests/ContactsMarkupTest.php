<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Settings\Settings;

/**
 * WIDGETS §12.1: the organisation's telephone, e-mail, address, place and hours come from the
 * Contacts tab of the settings, not from fields of SEO's own.
 */
final class ContactsMarkupTest extends TestCase
{
    #[Test]
    public function the_organisation_says_what_the_contacts_tab_says(): void
    {
        app(Settings::class)->save([
            'seo.org-name' => ['ru' => 'Acme'],
            'seo.org-socials' => [['url' => 'https://www.facebook.com/acme']],
            'contacts.phones' => [
                ['number' => '+1 202 555 0143', 'primary' => false],
                ['number' => '+44 20 7946 0958', 'primary' => true],
            ],
            'contacts.emails' => [['email' => 'hello@example.com']],
            'contacts.socials' => [
                ['network' => 'facebook', 'url' => 'https://www.facebook.com/acme'],
                ['network' => 'instagram', 'url' => 'https://www.instagram.com/acme'],
            ],
        ]);

        $organisation = $this->organisation();

        $this->assertSame('Organization', $organisation['@type']);
        $this->assertSame('+442079460958', $organisation['telephone']);
        $this->assertSame('hello@example.com', $organisation['email']);
        // The network typed into SEO before the tab existed and the tab's, each once.
        $this->assertSame(['https://www.facebook.com/acme', 'https://www.instagram.com/acme'], $organisation['sameAs']);
        $this->assertArrayNotHasKey('openingHoursSpecification', $organisation);
    }

    #[Test]
    public function a_place_with_hours_is_a_local_business(): void
    {
        app(Settings::class)->save([
            'seo.org-name' => ['ru' => 'Acme'],
            'contacts.addresses' => [['address' => ['ru' => '1 Example Street, London'], 'latitude' => 51.5074, 'longitude' => -0.1278, 'primary' => true]],
            'contacts.hours' => [
                ['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'opens' => '09:00', 'closes' => '19:00'],
                ['days' => ['sat'], 'opens' => '22:00', 'closes' => '02:00'],
            ],
            'contacts.hours-exceptions' => [['date' => now()->addDays(3)->format('Y-m-d'), 'closed' => true]],
        ]);

        $organisation = $this->organisation();

        $this->assertSame('LocalBusiness', $organisation['@type']);
        $this->assertSame('1 Example Street, London', $organisation['address']);
        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => 51.5074, 'longitude' => -0.1278], $organisation['geo']);
        $this->assertSame([
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['https://schema.org/Monday', 'https://schema.org/Tuesday', 'https://schema.org/Wednesday', 'https://schema.org/Thursday', 'https://schema.org/Friday'],
                'opens' => '09:00',
                'closes' => '19:00',
            ],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['https://schema.org/Saturday'], 'opens' => '22:00', 'closes' => '02:00'],
            [
                '@type' => 'OpeningHoursSpecification',
                'validFrom' => now()->addDays(3)->format('Y-m-d'),
                'validThrough' => now()->addDays(3)->format('Y-m-d'),
                'opens' => '00:00',
                'closes' => '00:00',
            ],
        ], $organisation['openingHoursSpecification']);
    }

    /**
     * @return array<string, mixed>
     */
    private function organisation(): array
    {
        foreach (app(Seo::class)->for('/', null, 'ru')->jsonLd as $block) {
            if (in_array($block['@type'] ?? null, ['Organization', 'LocalBusiness'], true)) {
                return $block;
            }
        }

        $this->fail('No organisation block.');
    }
}
