<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * The picture of a card with the media library on the site (FORM_STEPS §5.1): picked by id or by
 * key, kept by key, drawn with the address the library gives now — for every card of a form in one
 * query — and a file gone from the library is a card without a picture, not a 500.
 */
final class CardsWithMediaTest extends TestCase
{
    use CountsQueries;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), MediaServiceProvider::class];
    }

    #[Test]
    public function a_picture_named_by_id_is_kept_by_its_key(): void
    {
        $file = $this->mediaFile('media/ab/viewing.png');
        $form = $this->form();

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields"), [
                'name' => 'purpose',
                'type' => 'radio',
                'title' => ['en' => 'Purpose'],
                'options' => ['view' => 'cards', 'choices' => [
                    ['value' => 'viewing', 'label' => 'Viewing', 'image' => ['id' => $file->getKey()]],
                    ['value' => 'question', 'label' => 'Question', 'image' => ['id' => 999]],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.options.choices.0.image', ['path' => 'media/ab/viewing.png'])
            ->assertJsonMissingPath('data.options.choices.1.image');
    }

    #[Test]
    public function the_page_draws_every_picture_in_one_query_and_skips_one_that_is_gone(): void
    {
        $this->mediaFile('media/ab/viewing.png');
        $this->mediaFile('media/ab/parking.png');

        $this->form('contact', [
            ['name' => 'purpose', 'type' => FieldType::Radio, 'options' => ['view' => 'cards', 'choices' => [
                ['value' => 'viewing', 'label' => 'Viewing', 'image' => ['path' => 'media/ab/viewing.png']],
                ['value' => 'question', 'label' => 'Question', 'image' => ['path' => 'media/ab/deleted.png']],
            ]]],
            ['name' => 'extras', 'type' => FieldType::Checkbox, 'options' => ['view' => 'cards', 'choices' => [
                ['value' => 'parking', 'label' => 'Parking', 'image' => ['path' => 'media/ab/parking.png']],
            ]]],
        ]);

        $tally = $this->recordQueries(fn (): string => Blade::render('<x-webx-inbox::form slug="contact" />'));
        $html = (string) $tally->result;

        $this->assertSame(2, substr_count($html, '<img class="webx-field__card-image"'));
        $this->assertMatchesRegularExpression('~<img class="webx-field__card-image" src="[^"]*media/ab/viewing\.png[^"]*" alt=""~', $html);
        $this->assertMatchesRegularExpression('~<img class="webx-field__card-image" src="[^"]*media/ab/parking\.png[^"]*" alt=""~', $html);
        $this->assertStringNotContainsString('deleted.png', $html);

        $queries = array_filter(
            $tally->queries,
            static fn (array $query): bool => str_contains($query['query'], 'media_files'),
        );
        $this->assertCount(1, $queries, 'The pictures of all the cards of a form are one query of the library.');
    }

    private function mediaFile(string $path): MediaFile
    {
        // Every file belongs to a folder, and the library's migration makes the root one.
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        /** @var MediaFile $file */
        $file = MediaFile::query()->forceCreate([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => md5($path),
            'name' => basename($path),
            'name_lower' => basename($path),
            'file_name' => basename($path),
            'extension' => 'png',
            'mime' => 'image/png',
            'size' => 2048,
            'width' => 640,
            'height' => 480,
        ]);

        return $file;
    }
}
