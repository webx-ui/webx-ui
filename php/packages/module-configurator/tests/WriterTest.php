<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Configurator\Panel\Refused;
use WebxUi\Media\Usage\MediaUsage;

/**
 * Drafts and versions are the frame's (decision 2); what is the module's own is checked here: a
 * draft saves with problems, a publication does not; the key is fixed once published; a picture
 * inside the document counts as a use of the library file.
 */
final class WriterTest extends TestCase
{
    #[Test]
    public function a_new_configurator_is_a_draft_and_the_site_has_nothing(): void
    {
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові'], $this->schema());

        $this->assertSame(Configurator::STATUS_DRAFT, $configurator->status());
        $this->assertNull($configurator->liveSchema());
        $this->assertSame('corner', $configurator->editing()['schema']['initial']['construction'] ?? null);
    }

    #[Test]
    public function a_draft_with_problems_is_saved_and_its_publication_refused(): void
    {
        $this->mediaFile();
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові']);

        // The skeleton: no steps yet.
        $this->assertSame('steps.empty', $this->writer()->problems($configurator)[0]->code);

        try {
            $this->writer()->publish($configurator);
            $this->fail('A schema with problems was published.');
        } catch (Refused $refused) {
            $this->assertSame('steps.empty', $refused->problems[0]->code);
        }

        $this->assertFalse($configurator->refresh()->isPublished());

        $this->writer()->update($configurator, ['schema' => $this->schema()]);
        $this->writer()->publish($configurator);

        $this->assertSame(Configurator::STATUS_PUBLISHED, $configurator->refresh()->status());
        $this->assertSame(1, $configurator->liveVersion());
        $this->assertSame('UAH', $configurator->liveSchema()['currency'] ?? null);
    }

    #[Test]
    public function the_key_is_a_unique_slug_and_is_fixed_once_published(): void
    {
        $this->mediaFile();
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові'], $this->schema());

        $this->assertRefused(fn () => $this->writer()->create('shower', ['uk' => 'Ще одна']), 'is taken');
        $this->assertRefused(fn () => $this->writer()->create('Shower Cabins', ['uk' => 'Ще одна']), 'slug');
        $this->assertRefused(fn () => $this->writer()->create('doors', ['en' => 'Doors']), 'default language [uk]');

        $this->writer()->update($configurator, ['key' => 'cabins']);
        $this->assertSame('cabins', $configurator->refresh()->key);

        $this->writer()->publish($configurator);
        $this->assertRefused(fn () => $this->writer()->update($configurator, ['key' => 'showers']), 'does not change');

        // Rolled back, still the same key: a version is content, and the key is not.
        $this->writer()->update($configurator, ['title' => ['uk' => 'Кабіни']]);
        $this->writer()->publish($configurator);
        $configurator->restoreVersion(1);
        $this->assertSame('cabins', $configurator->refresh()->key);
        $this->assertSame('Душові', $configurator->editing()['title']['uk']);
    }

    #[Test]
    public function without_the_inbox_a_form_is_refused(): void
    {
        $this->assertRefused(fn () => $this->writer()->create('shower', ['uk' => 'Душові'], null, 'request'), 'no inbox');
    }

    #[Test]
    public function a_picture_in_the_document_is_a_use_of_the_library_file(): void
    {
        $file = $this->mediaFile('media/ab/cd/corner.svg');
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові'], $this->schema('media/ab/cd/corner.svg'));
        $this->writer()->publish($configurator);

        $places = $this->app->make(MediaUsage::class)->of(new Collection([$file]));

        $this->assertContains('configurators', array_column($places[$file->getKey()] ?? [], 'table'));
    }

    private function assertRefused(callable $write, string $words): void
    {
        try {
            $write();
            $this->fail("Expected a refusal with «{$words}».");
        } catch (Refused $refused) {
            $this->assertStringContainsString($words, $refused->getMessage());
        }
    }

    private function writer(): ConfiguratorWriter
    {
        return $this->app->make(ConfiguratorWriter::class);
    }
}
