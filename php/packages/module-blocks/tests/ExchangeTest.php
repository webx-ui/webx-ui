<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;

/**
 * Block types as files (§17): out with `webx:blocks:export`, back with `webx:blocks:import`.
 */
final class ExchangeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/blocks-'.bin2hex(random_bytes(4)));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function export_writes_the_published_version_of_each_type_and_import_reads_it_back(): void
    {
        $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>', ['group' => 'layout', 'allow' => null], [
            'sample' => ['title' => 'Welcome'],
            'styles' => '.b-hero { display: grid; }',
        ]);

        // A type with a draft only is not on the site, so it is not in the export.
        Block::query()->create(['slug' => 'sketch', 'title' => 'Sketch'])->saveVersion(['template' => '<p></p>']);

        $this->artisan('webx:blocks:export', ['--path' => $this->dir])
            ->expectsOutputToContain('sketch')
            ->assertSuccessful();

        $this->assertFileExists("{$this->dir}/hero.json");
        $this->assertFileDoesNotExist("{$this->dir}/sketch.json");

        $document = json_decode((string) file_get_contents("{$this->dir}/hero.json"), true);

        $this->assertSame('hero', $document['slug']);
        $this->assertSame('layout', $document['group']);
        $this->assertSame(1, $document['version']);
        $this->assertSame('<section data-wx-block="hero">{{ $title }}</section>', $document['template']);
        $this->assertSame(['title' => 'Welcome'], $document['sample']);
        $this->assertSame('wx-input', $document['schema'][0]['type']);

        // Another site: nothing there yet.
        Block::query()->where('slug', 'hero')->first()?->delete();

        $this->artisan('webx:blocks:import', ['--path' => $this->dir])
            ->expectsOutputToContain('created')
            ->assertSuccessful();

        $hero = Block::query()->where('slug', 'hero')->with(['draftVersion', 'publishedVersion'])->firstOrFail();

        $draft = $hero->draftVersion;

        $this->assertSame('layout', $hero->group);
        $this->assertInstanceOf(BlockVersion::class, $draft);
        $this->assertSame(1, $draft->number);
        $this->assertSame('import', $draft->source);
        $this->assertSame('Imported from hero.json', $draft->comment);
        $this->assertNull($hero->publishedVersion, 'imported as a draft, not put on the site by itself');

        // The same files again: nothing to write.
        $this->artisan('webx:blocks:import', ['--path' => $this->dir])
            ->expectsOutputToContain('unchanged')
            ->assertSuccessful();

        $this->assertSame(1, $hero->versions()->count());

        // With --publish the checks run and the draft goes live.
        $this->artisan('webx:blocks:import', ['--path' => $this->dir, '--publish' => true])
            ->expectsOutputToContain('published v1')
            ->assertSuccessful();

        $this->assertSame(1, $hero->refresh()->publishedVersion?->number);
    }

    #[Test]
    public function a_changed_file_writes_a_new_version_and_a_dry_run_only_says_so(): void
    {
        $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>');

        $this->artisan('webx:blocks:export', ['--path' => $this->dir])->assertSuccessful();

        $document = json_decode((string) file_get_contents("{$this->dir}/hero.json"), true);
        $document['template'] = '<section data-wx-block="hero" class="b-hero">{{ $title }}</section>';
        $document['title'] = 'Hero banner';
        file_put_contents("{$this->dir}/hero.json", json_encode($document));

        $this->artisan('webx:blocks:import', ['--path' => $this->dir, '--dry-run' => true])
            ->expectsOutputToContain('would write a version')
            ->assertSuccessful();

        $hero = Block::query()->where('slug', 'hero')->firstOrFail();

        $this->assertSame('Hero', $hero->title);
        $this->assertSame(1, $hero->versions()->count());

        $this->artisan('webx:blocks:import', ['slug' => ['hero'], '--path' => $this->dir])->assertSuccessful();

        $hero->refresh();

        $this->assertSame('Hero banner', $hero->title);
        $this->assertSame(2, $hero->draftVersion?->number);
        $this->assertSame(1, $hero->publishedVersion?->number);
    }

    #[Test]
    public function a_bad_file_is_refused_and_the_others_still_come_in(): void
    {
        File::ensureDirectoryExists($this->dir);
        file_put_contents("{$this->dir}/bad.json", json_encode(['slug' => 'Bad Slug', 'title' => 'Bad']));
        file_put_contents("{$this->dir}/good.json", json_encode(['title' => 'Good', 'template' => '<p data-wx-block="good"></p>']));
        file_put_contents("{$this->dir}/broken.json", '{not json');

        $this->artisan('webx:blocks:import', ['--path' => $this->dir])
            ->expectsOutputToContain('bad.json')
            ->expectsOutputToContain('broken.json')
            ->assertFailed();

        $this->assertSame(['good'], Block::query()->pluck('slug')->all(), 'the slug comes from the file name when the document has none');
    }

    #[Test]
    public function a_publish_that_fails_is_reported_and_leaves_a_draft(): void
    {
        File::ensureDirectoryExists($this->dir);
        file_put_contents("{$this->dir}/cursed.json", json_encode([
            'slug' => 'cursed',
            'title' => 'Cursed',
            'schema' => [['id' => 'title', 'type' => 'wx-input']],
            'template' => '<p data-wx-block="cursed">{{ nope($title) }}</p>',
            'sample' => ['title' => 'x'],
        ]));

        $this->artisan('webx:blocks:import', ['--path' => $this->dir, '--publish' => true])
            ->expectsOutputToContain('not published')
            ->assertFailed();

        $cursed = Block::query()->where('slug', 'cursed')->firstOrFail();

        $this->assertSame(1, $cursed->draftVersion?->number);
        $this->assertNull($cursed->publishedVersion);
    }

    #[Test]
    public function the_draft_flag_exports_the_version_being_edited(): void
    {
        $hero = $this->publish('hero', '<section data-wx-block="hero">v1</section>');
        $hero->saveVersion(['template' => '<section data-wx-block="hero">v2</section>']);

        $this->artisan('webx:blocks:export', ['slug' => ['hero'], '--path' => $this->dir])->assertSuccessful();
        $this->assertStringContainsString('v1', (string) file_get_contents("{$this->dir}/hero.json"));

        $this->artisan('webx:blocks:export', ['slug' => ['hero'], '--path' => $this->dir, '--draft' => true])->assertSuccessful();
        $this->assertStringContainsString('v2', (string) file_get_contents("{$this->dir}/hero.json"));

        $this->artisan('webx:blocks:export', ['slug' => ['ghost'], '--path' => $this->dir])->assertFailed();
    }
}
