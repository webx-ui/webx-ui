<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Submission;

/**
 * Attachments (§8): a disk of the module's own, and bytes that only the panel serves.
 */
final class FilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('inbox');
    }

    #[Test]
    public function it_puts_an_attachment_on_its_own_disk(): void
    {
        $form = $this->form('apply', [
            ['name' => 'cv', 'type' => FieldType::File, 'is_required' => true],
        ]);

        $this->postJson($this->intake('apply'), [
            'fields' => ['cv' => UploadedFile::fake()->create('resume.pdf', 20, 'application/pdf')],
        ])->assertOk();

        $submission = Submission::query()->sole();
        $file = $submission->files->sole();

        $this->assertSame('inbox', $file->disk);
        $this->assertStringStartsWith("inbox/{$form->getKey()}/{$submission->getKey()}/", $file->path);
        // The visitor's own name is kept as a label; the key on the disk is a uuid, so that
        // nothing about a file name can become a path.
        $this->assertSame('resume.pdf', $file->name);
        $this->assertStringEndsWith('.pdf', $file->path);
        Storage::disk('inbox')->assertExists($file->path);

        // The answer is still one line of text, so a file field is one row in the letter and
        // one column in the export like every other field.
        $this->assertSame('resume.pdf', $submission->value('cv')?->value);
    }

    #[Test]
    public function it_refuses_a_file_that_is_too_big_or_of_the_wrong_kind(): void
    {
        $this->form('apply', [
            ['name' => 'cv', 'type' => FieldType::File, 'options' => ['max_size' => 100, 'extensions' => ['pdf']]],
        ]);

        $this->postJson($this->intake('apply'), [
            'fields' => ['cv' => UploadedFile::fake()->create('resume.pdf', 500, 'application/pdf')],
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.cv']);

        $this->postJson($this->intake('apply'), [
            'fields' => ['cv' => UploadedFile::fake()->create('script.php', 1, 'text/x-php')],
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.cv']);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function a_field_cannot_ask_for_more_than_the_site_allows(): void
    {
        config(['webx-inbox.upload.max_size' => 100]);

        $this->form('apply', [
            ['name' => 'cv', 'type' => FieldType::File, 'options' => ['max_size' => 100000]],
        ]);

        $this->postJson($this->intake('apply'), [
            'fields' => ['cv' => UploadedFile::fake()->create('resume.pdf', 500, 'application/pdf')],
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.cv']);
    }

    #[Test]
    public function it_takes_several_files_for_a_field_that_asks_for_them(): void
    {
        $this->form('apply', [
            ['name' => 'docs', 'type' => FieldType::File, 'options' => ['multiple' => true]],
        ]);

        $this->postJson($this->intake('apply'), [
            'fields' => ['docs' => [
                UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 10, 'application/pdf'),
            ]],
        ])->assertOk();

        $submission = Submission::query()->sole();

        $this->assertCount(2, $submission->files);
        $this->assertSame('one.pdf, two.pdf', $submission->value('docs')?->value);
        $this->assertCount(2, (array) $submission->value('docs')->payload);
    }

    #[Test]
    public function the_panel_serves_the_bytes_and_only_to_somebody_allowed_to_read_them(): void
    {
        $this->form('apply', [['name' => 'cv', 'type' => FieldType::File]]);

        $this->postJson($this->intake('apply'), [
            'fields' => ['cv' => UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf')],
        ])->assertOk();

        $submission = Submission::query()->sole();
        $file = $submission->files->sole();
        $url = "/api/cms/inbox/submissions/{$submission->getKey()}/files/{$file->getKey()}";

        $this->getJson($url)->assertUnauthorized();

        $this->actingAs($this->editor([]), 'cms')->getJson($url)->assertForbidden();

        $this->actingAs($this->editor(['inbox.view']), 'cms')->get($url)
            ->assertOk()
            ->assertDownload('resume.pdf');
    }

    #[Test]
    public function a_file_of_another_submission_is_not_at_this_address(): void
    {
        $this->form('apply', [
            ['name' => 'name', 'type' => FieldType::Text],
            ['name' => 'cv', 'type' => FieldType::File],
        ]);

        foreach (['one', 'two'] as $name) {
            $this->postJson($this->intake('apply'), [
                'fields' => [
                    'name' => $name,
                    'cv' => UploadedFile::fake()->create("{$name}.pdf", 10, 'application/pdf'),
                ],
            ])->assertOk();
        }

        [$first, $second] = Submission::query()->orderBy('id')->get()->all();

        $this->actingAs($this->editor(['inbox.view']), 'cms')
            ->getJson("/api/cms/inbox/submissions/{$first->getKey()}/files/{$second->files->sole()->getKey()}")
            ->assertNotFound();
    }

    #[Test]
    public function deleting_a_submission_takes_its_files_off_the_disk(): void
    {
        $this->form('apply', [['name' => 'cv', 'type' => FieldType::File]]);

        $this->postJson($this->intake('apply'), [
            'fields' => ['cv' => UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf')],
        ])->assertOk();

        $submission = Submission::query()->sole();
        $path = $submission->files->sole()->path;

        $submission->delete();

        // The rows cascade in the database, which raises no model events — so the bytes are
        // deleted by hand or they stay forever with nothing pointing at them.
        Storage::disk('inbox')->assertMissing($path);
    }

    #[Test]
    public function sending_the_same_thing_twice_does_not_leave_the_first_files_behind(): void
    {
        $this->form('apply', [
            ['name' => 'name', 'type' => FieldType::Text],
            ['name' => 'cv', 'type' => FieldType::File],
        ]);

        $paths = [];

        foreach (range(1, 2) as $attempt) {
            $this->postJson($this->intake('apply'), [
                'fields' => [
                    'name' => 'Ada',
                    'cv' => UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'),
                ],
            ])->assertOk();

            $paths[] = Submission::query()->sole()->files->last()?->path;
        }

        $this->assertSame(1, Submission::query()->count());
        $this->assertCount(1, Submission::query()->sole()->files);
        Storage::disk('inbox')->assertMissing((string) $paths[0]);
        Storage::disk('inbox')->assertExists((string) $paths[1]);
    }
}
