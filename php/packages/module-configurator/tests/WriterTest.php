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
        // Brought, not refused (#125): only nothing left, or a key taken however it is typed.
        $this->assertRefused(fn () => $this->writer()->create('!!!', ['uk' => 'Ще одна']), 'Nothing is left');
        $this->assertRefused(fn () => $this->writer()->create(' Shower ', ['uk' => 'Ще одна']), 'is taken');
        $this->assertSame('shower-cabins', $this->writer()->create('Shower Cabins', ['uk' => 'Ще одна'])->key);
        $this->assertRefused(fn () => $this->writer()->create('doors', ['en' => 'Doors']), 'default language [uk]');

        $this->writer()->update($configurator, ['key' => 'Cabins']);
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

    #[Test]
    public function a_typed_key_of_the_schema_is_brought_with_what_names_it_and_a_known_one_stays(): void
    {
        $this->mediaFile();
        $schema = $this->schema();
        // Typed by hand, as an agent might: a step, a size field, an option value, and what names them.
        $schema['steps'][0]['key'] = 'Конструкція';
        $schema['steps'][0]['options'][0]['value'] = 'Кутова';
        $schema['steps'][0]['options'][0]['area'] = '(Ширина+d)*h/1e6';
        $schema['steps'][0]['options'][0]['defaults'] = ['h' => 2000, 'Ширина' => 900, 'd' => 900];
        $schema['steps'][0]['options'][1]['area'] = 'Ширина*h/1e6';
        $schema['steps'][0]['options'][1]['defaults'] = ['h' => 2000, 'Ширина' => 900];
        $schema['steps'][1]['fields'][1]['key'] = 'Ширина';
        $schema['steps'][1]['fields'][2]['when'] = ['Конструкція' => ['Кутова']];
        $schema['initial'] = ['Конструкція' => 'Кутова'];
        $schema['steps'][2]['key'] = '2 скла';

        $configurator = $this->writer()->create('shower', ['uk' => 'Душові'], $schema);
        $kept = $configurator->editing()['schema'];

        $this->assertSame(['konstruktsiia', 'size', 'step_2_skla', 'hardware'], array_column($kept['steps'], 'key'));
        $this->assertSame('kutova', $kept['steps'][0]['options'][0]['value']);
        $this->assertSame('(shyryna+d)*h/1e6', $kept['steps'][0]['options'][0]['area']);
        $this->assertSame(['h' => 2000, 'shyryna' => 900, 'd' => 900], $kept['steps'][0]['options'][0]['defaults']);
        $this->assertSame('shyryna', $kept['steps'][1]['fields'][1]['key']);
        $this->assertSame(['konstruktsiia' => ['kutova']], $kept['steps'][1]['fields'][2]['when']);
        $this->assertSame(['konstruktsiia' => 'kutova'], $kept['initial']);
        $this->assertSame([], $this->writer()->problems($configurator->refresh()));

        // A name the document had already stays as it was, fitting or not: something may name it.
        $draft = $kept;
        $draft['steps'][3]['key'] = 'Фурнітура';
        $this->writer()->update($configurator, ['schema' => $draft]);
        $this->assertSame('furnitura', $configurator->refresh()->editing()['schema']['steps'][3]['key']);

        // A draft of before the bringing, saved with the problem: the key stays until it is renamed.
        $configurator->saveDraft(['schema' => $draft] + $configurator->editing());
        $this->writer()->update($configurator->refresh(), ['schema' => $draft]);
        $this->assertSame('Фурнітура', $configurator->refresh()->editing()['schema']['steps'][3]['key']);
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
