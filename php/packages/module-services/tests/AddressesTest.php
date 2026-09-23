<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Services\Models\Service;

/**
 * The two types in the registry on one level under one prefix (§4.3): where they land, what a
 * collision between them says, and what a rename leaves behind.
 */
final class AddressesTest extends TestCase
{
    #[Test]
    public function a_service_and_a_category_live_on_one_level(): void
    {
        $category = $this->category('implants');
        $service = $this->service('implants-turnkey');

        $this->assertSame('services/implants', $category->routeCanonical()?->path);
        $this->assertSame('services/implants-turnkey', $service->routeCanonical()?->path);

        // Filed into the category, the service keeps its address: the category is not part of it.
        $service->syncCategories([$category->getKey()]);

        $this->assertSame('services/implants-turnkey', $service->refresh()->routeCanonical()?->path);
    }

    #[Test]
    public function a_slug_taken_by_a_category_is_refused_under_the_field_with_its_name(): void
    {
        $this->category('implants')->update(['title' => 'Dental implants']);

        try {
            $this->service('implants');
            $this->fail('The second address should have been refused.');
        } catch (PathRejected $rejected) {
            $this->assertSame('slug', $rejected->attribute);
            $this->assertStringContainsString('Dental implants', $rejected->errors()['slug'][0]);
        }
    }

    #[Test]
    public function a_category_cannot_take_the_address_of_a_service_either(): void
    {
        $this->service('whitening');

        $this->expectException(PathRejected::class);

        $this->category('whitening');
    }

    #[Test]
    public function a_renamed_service_leaves_the_old_address_behind_as_a_301(): void
    {
        $service = $this->service('otbelivanie');

        $service->slug = 'whitening';
        $service->save();

        $this->get('/services/otbelivanie')->assertRedirect('/services/whitening')->assertStatus(301);
        $this->get('/services/whitening')->assertOk();
    }

    #[Test]
    public function a_service_with_no_slug_in_a_language_has_no_address_in_it(): void
    {
        $this->useLocales('en', 'uk');

        $service = new Service(['title' => ['en' => 'Whitening'], 'slug' => ['en' => 'whitening']]);
        $service->save();
        $service->publish();

        $this->assertSame('services/whitening', $service->routeCanonical('en')?->path);
        $this->assertNull($service->routeCanonical('uk'));
    }
}
