<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Boot\Plan;
use WebxUi\Admin\Boot\Step;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Schema;
use WebxUi\Blocks\Themes\TypeHash;
use WebxUi\Themes\Blocks\SchemaCompatibility;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\ThemeServiceProvider;

/**
 * The block types of the theme layers onto the site (BLOCK_SCHEMAS §1, §4): only by
 * `webx:blocks:import --theme`, asked by a person. A new type is created; a type the site has is
 * left as it is, whoever changed it, and said to differ; `--replace=<slug>` writes that one over
 * as a new version, and nothing writes them all over at once.
 *
 * And the other half of the rule: the deploy — `webx:boot` and the `webx:theme:sync` it and
 * `composer update` run — writes no block type at all.
 */
final class ImportThemeTest extends TestCase
{
    private string $dir;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ThemeServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->dir = str_replace('\\', '/', sys_get_temp_dir()).'/webx-import-theme-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->dir.'/local/blocks');
        File::ensureDirectoryExists($this->dir.'/default/blocks');
        File::put($this->dir.'/local/theme.json', '{"theme": {"title": "Local"}}');

        $app['config']->set('webx-themes.theme', $this->dir.'/local');
        $app->usePublicPath($this->dir.'/public');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->chain();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function a_type_the_site_does_not_have_is_created_published_and_marked_with_its_layer(): void
    {
        $this->layer('default', $this->hero());

        $this->import(['--publish' => true])
            ->expectsOutputToContain('theme layers: webx-ui/default, '.$this->dir.'/local')
            ->expectsOutputToContain('created · v1 · published v1')
            ->assertSuccessful();

        // Said and written: the row is there when the line says so.
        $hero = Block::query()->where('slug', 'hero')->with('publishedVersion')->firstOrFail();
        $this->assertSame('webx-ui/default', $hero->theme_source);
        $this->assertSame(TypeHash::ofDocument($this->hero()), $hero->theme_hash);
        $this->assertSame(BlockVersion::SOURCE_IMPORT, $hero->publishedVersion?->source);
        $this->assertSame('Imported from webx-ui/default', $hero->publishedVersion->comment ?? null);
    }

    #[Test]
    public function the_same_file_again_is_unchanged_and_so_is_an_export_of_it(): void
    {
        $this->layer('default', $this->hero());
        $this->import()->assertSuccessful();

        // What webx:blocks:export writes: the version number and the moment change, the type does not.
        $this->layer('default', $this->hero(['version' => 7, 'exported_at' => '2026-10-11T10:00:00+00:00']));

        $this->import()->expectsOutputToContain('unchanged')->assertSuccessful();
        $this->assertSame(1, BlockVersion::query()->count());
    }

    /** A new release of the theme no longer reaches the site by itself, touched or not. */
    #[Test]
    public function a_new_file_over_a_type_the_site_has_differs_and_is_kept(): void
    {
        $this->layer('default', $this->hero());
        $this->import()->assertSuccessful();

        $this->layer('default', $this->hero([
            'title' => 'Hero, again',
            'template' => "<section class=\"b-hero\" data-wx-block=\"hero\">\n<h1>{{ \$title }}</h1>\n</section>",
            'schema' => [
                ['id' => 'title', 'type' => 'wx-textarea', 'label' => 'Title'],
                ['id' => 'subtitle', 'type' => 'wx-input', 'label' => 'Subtitle'],
            ],
        ]));

        $this->import()
            ->expectsOutputToContain('differs, kept')
            ->expectsOutputToContain('+ subtitle  ~ title (wx-input → wx-textarea)')
            ->expectsOutputToContain('differs (+3 −1 lines)')
            ->expectsOutputToContain('title')
            ->expectsOutputToContain('php artisan webx:blocks:import --theme --replace=hero')
            ->assertSuccessful();

        $this->assertSame('Hero', Block::query()->where('slug', 'hero')->value('title'));
        $this->assertSame(1, BlockVersion::query()->count());
    }

    /** (а), (б): what a client changed by MCP stays, through the import and through the deploy. */
    #[Test]
    public function a_type_changed_by_mcp_is_kept_by_the_import_the_sync_and_the_boot(): void
    {
        $this->layer('default', $this->hero());
        $this->import()->assertSuccessful();

        $hero = Block::query()->where('slug', 'hero')->firstOrFail();
        $hero->saveVersion(['styles' => '.b-hero { padding: 2em; }'], BlockVersion::SOURCE_MCP);
        $hero->publish();

        $this->layer('default', $this->hero(['title' => 'Hero, again']));

        $this->import()->expectsOutputToContain('differs, kept')->assertSuccessful();
        $this->deploy();

        $hero = Block::query()->where('slug', 'hero')->with('publishedVersion')->firstOrFail();
        $this->assertSame('Hero', $hero->title);
        $this->assertSame(2, $hero->publishedVersion?->number);
        $this->assertSame('.b-hero { padding: 2em; }', $hero->publishedVersion->styles ?? null);
        $this->assertSame(2, BlockVersion::query()->count());
    }

    /** (а): a type deleted in the panel is the site's choice, and the deploy does not bring it back. */
    #[Test]
    public function a_type_deleted_on_the_site_is_not_brought_back_by_the_deploy(): void
    {
        $this->layer('default', $this->hero());
        $this->import()->assertSuccessful();

        Block::query()->where('slug', 'hero')->firstOrFail()->delete();

        $this->deploy();

        $this->assertFalse(Block::query()->where('slug', 'hero')->exists());
    }

    /** (в): one slug named, that one written over — a new version, the old one in the history. */
    #[Test]
    public function replace_writes_the_named_type_over_as_a_new_version_and_nothing_else(): void
    {
        $this->layer('default', $this->hero());
        $this->layer('default', $this->hero(['slug' => 'banner', 'title' => 'Banner', 'template' => '<section data-wx-block="banner">{{ $title }}</section>']));
        $this->import()->assertSuccessful();

        foreach (['hero', 'banner'] as $slug) {
            $type = Block::query()->where('slug', $slug)->firstOrFail();
            $type->saveVersion(['styles' => ".b-{$slug} { padding: 2em; }"], BlockVersion::SOURCE_MCP);
            $type->publish();
        }

        $this->layer('default', $this->hero(['title' => 'Hero, again']));

        $this->import(['--replace' => ['hero'], '--publish' => true])
            ->expectsOutputToContain('replaced · v2 → v3 · published v3')
            ->expectsOutputToContain('differs, kept')
            ->assertSuccessful();

        $hero = Block::query()->where('slug', 'hero')->with('publishedVersion')->firstOrFail();
        $this->assertSame('Hero, again', $hero->title);
        $this->assertSame(3, $hero->publishedVersion?->number);
        $this->assertSame('Replaced from webx-ui/default', $hero->publishedVersion->comment ?? null);
        $this->assertSame(BlockVersion::SOURCE_MCP, $hero->versions()->where('number', 2)->value('source'), 'the client\'s version stays in the history');

        $banner = Block::query()->where('slug', 'banner')->with('publishedVersion')->firstOrFail();
        $this->assertSame(2, $banner->publishedVersion?->number, 'not named, not touched');
    }

    /** (г): there is no way to write every type over at once. */
    #[Test]
    public function every_type_at_once_or_a_slug_the_layers_do_not_carry_is_refused_before_anything_is_written(): void
    {
        $this->layer('default', $this->hero());
        $this->import()->assertSuccessful();
        $this->layer('default', $this->hero(['title' => 'Hero, again']));

        foreach (['*', 'all', ''] as $everything) {
            $this->import(['--replace' => [$everything]])
                ->expectsOutputToContain('there is no replacing every type at once')
                ->assertFailed();
        }

        $this->import(['--replace' => ['heor']])
            ->expectsOutputToContain('heor is not among the types read')
            ->assertFailed();

        $this->assertSame('Hero', Block::query()->where('slug', 'hero')->value('title'));
        $this->assertSame(1, BlockVersion::query()->count());

        $this->assertArrayNotHasKey('force', $this->app->make('Illuminate\Contracts\Console\Kernel')->all()['webx:blocks:import']->getDefinition()->getOptions());
    }

    #[Test]
    public function a_draft_open_on_the_site_is_what_the_file_is_compared_with_and_said(): void
    {
        $this->layer('default', $this->hero());
        $this->import()->assertSuccessful();

        Block::query()->where('slug', 'hero')->firstOrFail()->saveVersion(['styles' => '.b-hero { padding: 3em; }']);

        $this->import()
            ->expectsOutputToContain('differs, kept — a draft is open (v2)')
            ->expectsOutputToContain('differ (+3 −1 lines)')
            ->assertSuccessful();
    }

    /** What a module offered under a slug the theme now carries: a difference, not a refusal. */
    #[Test]
    public function a_type_of_the_site_by_the_same_slug_differs_and_is_kept(): void
    {
        $own = new Block(['slug' => 'hero', 'title' => 'Our hero']);
        $own->saveVersion(['template' => '<div data-wx-block="hero">ours</div>']);
        $own->publish();

        $this->layer('default', $this->hero());

        $this->import()->expectsOutputToContain('differs, kept')->assertSuccessful();

        $this->assertSame('Our hero', $own->fresh()?->title);
        $this->assertNull(Block::query()->where('slug', 'hero')->value('theme_source'));

        $this->import(['--replace' => ['hero'], '--publish' => true])->assertSuccessful();

        $this->assertSame('webx-ui/default', Block::query()->where('slug', 'hero')->value('theme_source'));
    }

    #[Test]
    public function a_type_whose_styles_break_the_tokens_rule_fails_and_is_not_written(): void
    {
        $this->layer('default', $this->hero(['styles' => ".b-hero {\n    color: #ff0000;\n}\n"]));

        $this->import()
            ->expectsOutputToContain('its styles break THEMES §8 (1 finding; line 2: literal colour #ff0000')
            ->assertFailed();

        $this->assertFalse(Block::query()->where('slug', 'hero')->exists());
    }

    #[Test]
    public function a_type_that_does_not_publish_is_said_and_left_a_draft(): void
    {
        $this->layer('default', $this->hero(['template' => '<div data-wx-block="hero">{{ $nowhere->at->all }}</div>']));

        $this->import(['--publish' => true])->expectsOutputToContain('not published')->assertFailed();

        $hero = Block::query()->where('slug', 'hero')->firstOrFail();
        $this->assertNull($hero->published_version_id);
        $this->assertNotNull($hero->draft_version_id);
    }

    #[Test]
    public function the_upper_layer_wins_the_slug_and_an_incompatible_override_fails(): void
    {
        $this->layer('default', $this->hero());
        $this->layer('local', $this->hero(['title' => 'Local hero', 'schema' => [
            ['id' => 'title', 'type' => 'wx-input', 'label' => 'Title'],
            ['id' => 'subtitle', 'type' => 'wx-input', 'label' => 'Subtitle'],
        ]]));

        $this->import()->assertSuccessful();
        $this->assertSame('Local hero', Block::query()->where('slug', 'hero')->value('title'));
        $this->assertSame($this->dir.'/local', Block::query()->where('slug', 'hero')->value('theme_source'));

        $this->layer('local', $this->hero(['schema' => [['id' => 'title', 'type' => 'wx-textarea', 'label' => 'Title']]]));

        $this->import()->expectsOutputToContain('field "title" was wx-input, is wx-textarea')->assertFailed();
    }

    #[Test]
    public function a_dry_run_says_and_writes_nothing(): void
    {
        $this->layer('default', $this->hero());

        $this->import(['--dry-run' => true])
            ->expectsOutputToContain('would create')
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->assertSuccessful();

        $this->assertSame(0, Block::query()->count());
    }

    #[Test]
    public function the_theme_and_a_directory_are_two_sources_and_a_slug_narrows_the_set(): void
    {
        $this->layer('default', $this->hero());
        $this->layer('default', $this->hero(['slug' => 'banner', 'title' => 'Banner', 'template' => '<section data-wx-block="banner"></section>']));

        $this->import(['--path' => $this->dir])->expectsOutputToContain('two sources')->assertFailed();
        $this->import(['slug' => ['nope']])->expectsOutputToContain('carry no type nope')->assertFailed();

        $this->import(['slug' => ['banner']])->assertSuccessful();
        $this->assertSame(['banner'], Block::query()->pluck('slug')->all());
    }

    /** The sync is files: with and without `--force`, it creates and changes no type. */
    #[Test]
    public function the_sync_writes_no_block_type_forced_or_not(): void
    {
        $this->layer('default', $this->hero());

        $this->artisan('webx:theme:sync')->doesntExpectOutputToContain('Block types')->assertSuccessful();
        $this->artisan('webx:theme:sync', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Block::query()->count());
    }

    #[Test]
    public function the_engine_knows_the_same_layout_nodes_as_this_package(): void
    {
        $this->assertSame(Schema::LAYOUT, SchemaCompatibility::LAYOUT);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function import(array $options = []): PendingCommand
    {
        $command = $this->artisan('webx:blocks:import', ['--theme' => true] + $options);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    /**
     * What a deploy runs that could reach the tables: the steps of `webx:boot` for this site, of
     * which none may be about block types, and `webx:theme:sync`, which every deploy and every
     * `composer update` runs — forced too.
     */
    private function deploy(): void
    {
        $steps = $this->app->make(Plan::class)->steps(cache: false);

        $this->assertSame([], array_values(array_filter($steps, static fn (Step $step): bool => str_starts_with($step->command[0], 'webx:blocks:'))));

        $this->artisan('webx:theme:sync')->assertSuccessful();
        $this->artisan('webx:theme:sync', ['--force' => true])->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function layer(string $layer, array $document): void
    {
        File::put($this->dir."/{$layer}/blocks/{$document['slug']}.json", (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function hero(array $with = []): array
    {
        return $with + [
            'slug' => 'hero',
            'title' => 'Hero',
            'group' => 'content',
            'schema' => [['id' => 'title', 'type' => 'wx-input', 'label' => 'Title']],
            'template' => '<section class="b-hero" data-wx-block="hero">{{ $title }}</section>',
            'styles' => ".b-hero {\n    color: var(--site-color-text);\n}\n",
            'script' => null,
            'sample' => ['title' => 'Hello'],
        ];
    }

    /** The site's local theme over a packaged one, the way `webx:setup` lays them. */
    private function chain(): ThemeChain
    {
        $chain = new ThemeChain([
            ThemeManifest::fromLocal($this->dir.'/local', $this->dir.'/local'),
            new ThemeManifest('webx-ui/default', $this->dir.'/default', false),
        ]);
        $this->app->instance(ThemeChain::class, $chain);

        return $chain;
    }
}
