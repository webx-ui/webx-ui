<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * Fields a project patches onto the screens of this module (§3.4): stored in `extra`, through the
 * draft for a service and at once for a category, and read back the way the field's type says.
 *
 * The saves through the panel's API are session B's; these go through the model and
 * `CategoryForm`, which is the same code the API ends in.
 */
final class ProjectFieldsTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-services.categories.blocks', true);
    }

    #[Test]
    public function a_field_of_the_project_waits_in_the_draft_and_is_published_with_the_text(): void
    {
        $this->addField(Service::SCREEN, ['id' => 'price-from', 'type' => 'wx-input-number', 'name' => 'price-from', 'label' => 'Price from']);

        $service = $this->service('crowns');
        $service->saveDraft(['extra' => ['price-from' => 450]]);

        $this->assertNull($service->refresh()->extra('price-from'));

        $service->publish();

        $this->assertSame(450, $service->refresh()->extra('price-from'));
    }

    #[Test]
    public function merging_one_field_keeps_the_others(): void
    {
        $this->addField(Service::SCREEN, ['id' => 'price-from', 'type' => 'wx-input-number', 'name' => 'price-from', 'label' => 'Price from']);
        $this->addField(Service::SCREEN, ['id' => 'duration', 'type' => 'wx-input', 'name' => 'duration', 'label' => 'Duration']);

        $service = $this->service('crowns');
        $service->mergeExtra(['price-from' => 450])->save();
        $service->mergeExtra(['duration' => 'One visit'])->save();

        $this->assertSame(['price-from' => 450, 'duration' => 'One visit'], $service->refresh()->extraRaw());
    }

    #[Test]
    public function a_localized_field_is_read_in_the_language_of_the_page(): void
    {
        $this->useLocales('en', 'uk');
        $this->addField(Service::SCREEN, ['id' => 'duration', 'type' => 'wx-input', 'name' => 'duration', 'label' => 'Duration', 'localized' => true]);

        $service = $this->service('crowns');
        $service->mergeExtra(['duration' => ['en' => 'One visit', 'uk' => 'Один візит']])->save();

        $this->assertSame('One visit', $service->extra('duration', 'en'));
        $this->assertSame('Один візит', $service->extra('duration', 'uk'));
    }

    #[Test]
    public function a_picture_is_printed_as_an_address_rather_than_a_key(): void
    {
        $this->addField(Service::SCREEN, ['id' => 'badge', 'type' => 'wx-media', 'name' => 'badge', 'label' => 'Badge']);

        $file = $this->picture();
        $service = $this->service('crowns');
        $service->mergeExtra(['badge' => ['path' => $file->path]])->save();

        $badge = $service->extra('badge');

        $this->assertIsArray($badge);
        $this->assertIsString($badge['url']);
        $this->assertStringContainsString($file->path, $badge['url']);
        $this->assertSame(['path' => $file->path], $service->extraRaw('badge'));
    }

    #[Test]
    public function a_category_saves_a_field_of_the_project_at_once_and_the_next_save_keeps_it(): void
    {
        $this->addField(ServiceCategory::SCREEN, ['id' => 'badge-text', 'type' => 'wx-input', 'name' => 'badge-text', 'label' => 'Badge']);

        $category = $this->category('implants');
        $form = $this->app->make(CategoryForm::class);

        $form->save($category, ['badge-text' => 'New']);
        $form->save($category->refresh(), ['title' => ['en' => 'Dental implants']]);

        $category->refresh();

        $this->assertSame('New', $category->extra('badge-text'));
        $this->assertSame('Dental implants', $category->getTranslation('title', 'en'));
    }

    #[Test]
    public function the_blocks_tab_of_a_category_is_there_when_the_site_asks_for_it(): void
    {
        $ids = array_column(Tree::children(Screens::tree(ServiceCategory::SCREEN)[0]), 'id');

        $this->assertSame(['content', 'blocks-tab', 'image', 'seo'], $ids);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function addField(string $screen, array $node): void
    {
        Screens::extend($screen, [['op' => 'add', 'target' => 'project-fields', 'node' => $node]]);
    }

    private function picture(): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/badge.png',
            'hash' => str_repeat('b', 32),
            'name' => 'A badge',
            'file_name' => 'badge.png',
            'extension' => 'png',
            'mime' => 'image/png',
            'size' => 512,
            'width' => 64,
            'height' => 64,
        ]);
    }
}
