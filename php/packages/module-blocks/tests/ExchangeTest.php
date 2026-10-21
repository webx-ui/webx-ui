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

        // --publish is for what the import writes: a type the site has, unchanged, is not its to publish.
        $this->artisan('webx:blocks:import', ['--path' => $this->dir, '--publish' => true])
            ->doesntExpectOutputToContain('published v1')
            ->assertSuccessful();

        $this->assertNull($hero->refresh()->publishedVersion);

        // On a site without it, the checks run and the new type goes live.
        $hero->delete();

        $this->artisan('webx:blocks:import', ['--path' => $this->dir, '--publish' => true])
            ->expectsOutputToContain('created · v1 · published v1')
            ->assertSuccessful();

        $this->assertSame(1, Block::query()->where('slug', 'hero')->firstOrFail()->publishedVersion?->number);
    }

    #[Test]
    public function a_changed_file_is_kept_out_until_its_type_is_named_and_a_dry_run_only_says_so(): void
    {
        $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>');

        $this->artisan('webx:blocks:export', ['--path' => $this->dir])->assertSuccessful();

        $document = json_decode((string) file_get_contents("{$this->dir}/hero.json"), true);
        $document['template'] = '<section data-wx-block="hero" class="b-hero">{{ $title }}</section>';
        $document['title'] = 'Hero banner';
        file_put_contents("{$this->dir}/hero.json", json_encode($document));

        // (б) The site's type stays as it is, and the lines say how the file differs.
        $this->artisan('webx:blocks:import', ['--path' => $this->dir])
            ->expectsOutputToContain('differs, kept')
            ->expectsOutputToContain('differs (+1 −1 lines)')
            ->expectsOutputToContain("--path={$this->dir} --replace=hero")
            ->assertSuccessful();

        $this->artisan('webx:blocks:import', ['--path' => $this->dir, '--replace' => ['hero'], '--dry-run' => true])
            ->expectsOutputToContain('would replace · would write a version')
            ->assertSuccessful();

        $hero = Block::query()->where('slug', 'hero')->firstOrFail();

        $this->assertSame('Hero', $hero->title);
        $this->assertSame(1, $hero->versions()->count());

        // (в) Named, it is written over as a new draft version.
        $this->artisan('webx:blocks:import', ['slug' => ['hero'], '--path' => $this->dir, '--replace' => ['hero']])
            ->expectsOutputToContain('replaced · v1 → v2')
            ->assertSuccessful();

        $hero->refresh();

        $this->assertSame('Hero banner', $hero->title);
        $this->assertSame(2, $hero->draftVersion?->number);
        $this->assertSame('Replaced from hero.json', $hero->draftVersion->comment);
        $this->assertSame(1, $hero->publishedVersion?->number);

        // (г) Never all of them at once.
        $this->artisan('webx:blocks:import', ['--path' => $this->dir, '--replace' => ['*']])
            ->expectsOutputToContain('there is no replacing every type at once')
            ->assertFailed();
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

    #[Test]
    public function the_panel_exports_a_pack_with_the_components_the_chosen_types_call(): void
    {
        $this->publish('badge', '<span class="b-badge">{{ $label }}</span>', ['kind' => 'component']);
        $this->publish('hero', '<section data-wx-block="hero"><x-webx-block type="badge" :label="$title" /></section>');
        $this->publish('quote', '<blockquote data-wx-block="quote">{{ $text }}</blockquote>');
        Block::query()->create(['slug' => 'sketch', 'title' => 'Sketch'])->saveVersion(['template' => '<p></p>']);

        $response = $this->actingAs($this->editor(['blocks.view']), 'cms')
            ->getJson('/api/cms/blocks/export?slugs[]=hero&slugs[]=sketch&slugs[]=ghost')
            ->assertOk();

        $this->assertSame('webx-blocks', $response->json('data.format'));
        // What is called comes first, so a reader meets the component before its caller.
        $this->assertSame(['badge', 'hero'], array_column($response->json('data.blocks'), 'slug'));
        $this->assertSame(['sketch'], $response->json('skipped'));
        $this->assertSame(['ghost'], $response->json('missing'));

        $drafts = $this->actingAs($this->editor(['blocks.view']), 'cms')
            ->getJson('/api/cms/blocks/export?slugs[]=sketch&draft=1')
            ->assertOk();

        $this->assertSame(['sketch'], array_column($drafts->json('data.blocks'), 'slug'));
    }

    #[Test]
    public function the_panel_imports_a_pack_after_showing_what_it_would_do(): void
    {
        $this->publish('badge', '<span class="b-badge">{{ $label }}</span>', ['kind' => 'component']);
        $this->publish('hero', '<section data-wx-block="hero"><x-webx-block type="badge" :label="$title" /></section>');

        $editor = $this->editor();
        $pack = $this->actingAs($editor, 'cms')->getJson('/api/cms/blocks/export')->json('data');

        // Another site: nothing there yet.
        Block::query()->where('slug', 'hero')->first()?->delete();
        Block::query()->where('slug', 'badge')->first()?->delete();

        $plan = $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => $pack, 'name' => 'catalog.json', 'dry_run' => true])
            ->assertOk();

        $this->assertSame(['created', 'created'], array_column($plan->json('data'), 'status'));
        $this->assertSame(0, Block::query()->count(), 'a dry run writes nothing');

        $done = $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => $pack, 'name' => 'catalog.json', 'publish' => true])
            ->assertOk();

        $this->assertSame(['badge', 'hero'], array_column($done->json('data'), 'slug'));
        $this->assertSame([1, 1], array_column($done->json('data'), 'published'));

        $hero = Block::query()->where('slug', 'hero')->with('publishedVersion')->firstOrFail();

        $published = $hero->publishedVersion;

        $this->assertInstanceOf(BlockVersion::class, $published);
        $this->assertSame('import', $published->source);
        $this->assertSame('Imported from catalog.json', $published->comment);

        $again = $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => $pack, 'dry_run' => true])
            ->assertOk();

        $this->assertSame(['unchanged', 'unchanged'], array_column($again->json('data'), 'status'));
    }

    #[Test]
    public function an_exported_pack_imported_unchanged_writes_nothing_whitespace_included(): void
    {
        // What every stored template looks like: a trailing newline, and strings around it that
        // the stock middleware would trim.
        $this->publish('hero', "<section data-wx-block=\"hero\">{{ \$title }}</section>\n", [], [
            'sample' => ['title' => ' Welcome '],
            'styles' => ".b-hero { display: grid; }\n",
        ]);

        $editor = $this->editor();
        $pack = $this->actingAs($editor, 'cms')->getJson('/api/cms/blocks/export?slugs[]=hero')->json('data');

        $this->assertStringEndsWith("\n", $pack['blocks'][0]['template']);

        // As JSON, the way an API caller sends it, and as the text of the file, the way the panel does.
        foreach ([$pack, (string) json_encode($pack)] as $file) {
            $plan = $this->actingAs($editor, 'cms')
                ->postJson('/api/cms/blocks/import', ['file' => $file, 'name' => 'hero.json', 'dry_run' => true])
                ->assertOk();

            $this->assertSame('unchanged', $plan->json('data.0.status'));
            $this->assertFalse($plan->json('data.0.writes'));
        }

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => (string) json_encode($pack), 'name' => 'hero.json'])
            ->assertOk();

        $this->assertSame(1, Block::query()->where('slug', 'hero')->firstOrFail()->versions()->count());
    }

    #[Test]
    public function a_dry_run_that_would_publish_says_what_the_checks_would_hold_back(): void
    {
        $pack = ['blocks' => [
            ['slug' => 'fine', 'title' => 'Fine', 'template' => '<p data-wx-block="fine"></p>'],
            ['slug' => 'broken', 'title' => 'Broken', 'template' => '<div data-wx-block="broken">@if($x) <div></div>'],
        ]];

        $plan = $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => $pack, 'dry_run' => true, 'publish' => true])
            ->assertOk();

        $rows = array_column($plan->json('data'), null, 'slug');

        $this->assertSame('created', $rows['broken']['status']);
        $this->assertStringStartsWith('would not be published', (string) $rows['broken']['error']);
        $this->assertNull($rows['fine']['error']);
        $this->assertNull($rows['fine']['published'], 'nothing was kept, so there is no number to show');
        $this->assertSame(0, Block::query()->count(), 'the rehearsal is rolled back');
    }

    #[Test]
    public function the_panel_import_takes_a_single_file_refuses_anything_else_and_needs_manage(): void
    {
        $editor = $this->editor();
        $single = ['slug' => 'note', 'title' => 'Note', 'template' => '<p data-wx-block="note"></p>'];

        $this->actingAs($this->editor(['blocks.view']), 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => $single])
            ->assertForbidden();

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => $single, 'name' => 'note.json'])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'created');

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => ['blocks' => 'nope']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => ['blocks' => [
                ['slug' => 'ping', 'title' => 'Ping', 'kind' => 'component', 'template' => '<x-webx-block type="pong" />'],
                ['slug' => 'pong', 'title' => 'Pong', 'kind' => 'component', 'template' => '<x-webx-block type="ping" />'],
            ]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertNull(Block::query()->where('slug', 'ping')->first(), 'a circle is refused before anything is written');
    }

    /** BLOCK_SCHEMAS §5: the plan says how a type differs, and only the rows the person ticked are written over. */
    #[Test]
    public function the_panel_keeps_a_type_that_differs_unless_it_is_named_in_replace(): void
    {
        $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>');
        $this->publish('quote', '<blockquote data-wx-block="quote">{{ $text }}</blockquote>');

        $editor = $this->editor();
        $pack = $this->actingAs($editor, 'cms')->getJson('/api/cms/blocks/export')->json('data');

        foreach ($pack['blocks'] as $index => $document) {
            $pack['blocks'][$index]['title'] = $document['title'].', again';
            $pack['blocks'][$index]['schema'][] = ['id' => 'note', 'type' => 'wx-input'];
        }

        $plan = $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => $pack, 'dry_run' => true])
            ->assertOk();

        $rows = array_column($plan->json('data'), null, 'slug');
        $this->assertSame('differs', $rows['hero']['status']);
        $this->assertSame(['note'], $rows['hero']['diff']['fields']['added']);
        $this->assertSame(['title'], $rows['hero']['diff']['row']);
        $this->assertSame(1, $rows['hero']['from']);

        $done = $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blocks/import', ['file' => $pack, 'replace' => ['hero']])
            ->assertOk();

        $rows = array_column($done->json('data'), null, 'slug');
        $this->assertSame('replaced', $rows['hero']['status']);
        $this->assertSame(2, $rows['hero']['version']);
        $this->assertSame('differs', $rows['quote']['status']);
        $this->assertSame('Hero, again', Block::query()->where('slug', 'hero')->value('title'));
        $this->assertSame('Quote', Block::query()->where('slug', 'quote')->value('title'));

        // A slug the file does not carry, or all of them: refused before anything is written.
        foreach ([['nope'], ['*']] as $replace) {
            $this->actingAs($editor, 'cms')
                ->postJson('/api/cms/blocks/import', ['file' => $pack, 'replace' => [...$replace, 'quote']])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('replace');
        }

        $this->assertSame('Quote', Block::query()->where('slug', 'quote')->value('title'));
    }
}
