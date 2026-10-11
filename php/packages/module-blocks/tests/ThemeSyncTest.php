<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Schema;
use WebxUi\Blocks\Themes\LayerSync;
use WebxUi\Blocks\Themes\TypeHash;
use WebxUi\Themes\Blocks\SchemaCompatibility;
use WebxUi\Themes\Sync\SyncLine;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\ThemeServiceProvider;

/**
 * The block types of the theme layers on the site (THEMES §9.2): every branch of the six steps,
 * what is kept and what is refused, and the exit code of `webx:theme:sync`.
 */
final class ThemeSyncTest extends TestCase
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

        $this->dir = str_replace('\\', '/', sys_get_temp_dir()).'/webx-theme-sync-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->dir.'/local/blocks');
        File::ensureDirectoryExists($this->dir.'/default/blocks');
        File::put($this->dir.'/local/theme.json', '{"theme": {"title": "Local"}}');

        $app['config']->set('webx-themes.theme', $this->dir.'/local');
        $app->usePublicPath($this->dir.'/public');
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

        $this->assertLine('hero', 'created from webx-ui/default', $this->sync());

        $hero = Block::query()->where('slug', 'hero')->with('publishedVersion')->firstOrFail();
        $this->assertSame('webx-ui/default', $hero->theme_source);
        $this->assertSame(TypeHash::ofDocument($this->hero()), $hero->theme_hash);
        $this->assertSame(BlockVersion::SOURCE_THEME, $hero->publishedVersion?->source);
        $this->assertSame('From webx-ui/default', $hero->publishedVersion->comment ?? null);
        $this->assertSame($hero->theme_hash, TypeHash::ofPublished($hero), 'the file and the tables hash alike');
    }

    #[Test]
    public function the_same_file_again_changes_nothing(): void
    {
        $this->layer('default', $this->hero());
        $this->sync();

        $this->assertLine('hero', 'unchanged', $this->sync());
        $this->assertSame(1, BlockVersion::query()->count());
    }

    #[Test]
    public function an_export_of_the_same_type_is_the_same_type(): void
    {
        $this->layer('default', $this->hero());
        $this->sync();

        // What webx:blocks:export writes: the version number and the moment change, the type does not.
        $this->layer('default', $this->hero(['version' => 7, 'exported_at' => '2026-10-11T10:00:00+00:00']));

        $this->assertLine('hero', 'unchanged', $this->sync());
    }

    #[Test]
    public function a_new_file_over_a_type_the_site_has_not_touched_is_a_new_version(): void
    {
        $this->layer('default', $this->hero());
        $this->sync();

        $this->layer('default', $this->hero(['template' => '<section class="b-hero" data-wx-block="hero"><h1>{{ $title }}</h1></section>']));

        $this->assertLine('hero', 'updated from webx-ui/default', $this->sync());

        $hero = Block::query()->where('slug', 'hero')->with('publishedVersion')->firstOrFail();
        $this->assertSame(2, $hero->publishedVersion?->number);
        $this->assertStringContainsString('<h1>', (string) ($hero->publishedVersion->template ?? ''));
    }

    #[Test]
    public function a_type_changed_on_this_site_is_kept_unless_forced(): void
    {
        $this->layer('default', $this->hero());
        $this->sync();

        $hero = Block::query()->where('slug', 'hero')->firstOrFail();
        $hero->saveVersion(['styles' => '.b-hero { padding: 2em; }']);
        $hero->publish();

        $this->layer('default', $this->hero(['title' => 'Hero, again']));

        $this->assertLine('hero', 'changed on this site since the theme last set it, kept (--force overwrites)', $this->sync());
        $this->assertSame(2, BlockVersion::query()->count());
        $this->assertSame('Hero', $hero->fresh()?->title);

        $this->assertLine('hero', 'overwritten from webx-ui/default', $this->sync(force: true));
        $this->assertSame('Hero, again', Block::query()->where('slug', 'hero')->value('title'));
    }

    #[Test]
    public function a_draft_started_in_the_panel_is_a_change_too(): void
    {
        $this->layer('default', $this->hero());
        $this->sync();

        Block::query()->where('slug', 'hero')->firstOrFail()->saveVersion(['styles' => '.b-hero { padding: 3em; }']);
        $this->layer('default', $this->hero(['title' => 'Hero, again']));

        $this->assertLine('hero', 'changed on this site since the theme last set it, kept (--force overwrites)', $this->sync());
    }

    #[Test]
    public function a_type_of_the_site_by_the_same_slug_is_refused_even_with_force(): void
    {
        $own = new Block(['slug' => 'hero', 'title' => 'Our hero']);
        $own->saveVersion(['template' => '<div data-wx-block="hero">ours</div>']);
        $own->publish();

        $this->layer('default', $this->hero());

        foreach ([false, true] as $force) {
            $line = $this->line('hero', $this->sync(force: $force));
            $this->assertTrue($line->refused);
            $this->assertStringContainsString('this site has a type of its own by this slug', $line->detail);
        }

        $this->assertSame('Our hero', $own->fresh()?->title);
        $this->assertNull(Block::query()->where('slug', 'hero')->value('theme_source'));
        $this->assertSame(1, BlockVersion::query()->count());
    }

    #[Test]
    public function a_type_that_left_the_layers_is_kept(): void
    {
        $this->layer('default', $this->hero());
        $this->sync();

        File::delete($this->dir.'/default/blocks/hero.json');

        $this->assertLine('hero', 'webx-ui/default no longer carries it — kept, pages may stand on it', $this->sync());
        $this->assertTrue(Block::query()->where('slug', 'hero')->exists());
    }

    #[Test]
    public function a_type_whose_styles_break_the_tokens_rule_is_refused_and_not_written(): void
    {
        $this->layer('default', $this->hero(['styles' => ".b-hero {\n    color: #ff0000;\n}\n"]));

        $line = $this->line('hero', $this->sync());

        $this->assertTrue($line->refused);
        $this->assertStringContainsString('its styles break THEMES §8 (1 finding; line 2: literal colour #ff0000', $line->detail);
        $this->assertFalse(Block::query()->where('slug', 'hero')->exists());
    }

    #[Test]
    public function a_type_that_does_not_publish_leaves_nothing_behind(): void
    {
        $this->layer('default', $this->hero(['template' => '<div data-wx-block="hero">{{ $nowhere->at->all }}</div>']));

        $line = $this->line('hero', $this->sync());

        $this->assertTrue($line->refused);
        $this->assertStringContainsString('it does not publish', $line->detail);
        $this->assertSame(0, Block::query()->count());
        $this->assertSame(0, BlockVersion::query()->count());
    }

    #[Test]
    public function the_upper_layer_wins_the_slug_and_an_incompatible_override_is_refused(): void
    {
        $this->layer('default', $this->hero());
        $this->layer('local', $this->hero(['title' => 'Local hero', 'schema' => [
            ['id' => 'title', 'type' => 'wx-input', 'label' => 'Title'],
            ['id' => 'subtitle', 'type' => 'wx-input', 'label' => 'Subtitle'],
        ]]));

        $this->assertLine('hero', 'created from '.$this->dir.'/local', $this->sync());
        $this->assertSame('Local hero', Block::query()->where('slug', 'hero')->value('title'));

        $this->layer('local', $this->hero(['schema' => [['id' => 'title', 'type' => 'wx-textarea', 'label' => 'Title']]]));

        $line = $this->line('hero', $this->sync());
        $this->assertTrue($line->refused);
        $this->assertStringContainsString('field "title" was wx-input, is wx-textarea', $line->detail);
    }

    #[Test]
    public function a_dry_run_says_and_writes_nothing(): void
    {
        $this->layer('default', $this->hero());

        $this->assertLine('hero', 'would be created from webx-ui/default', $this->sync(dryRun: true));
        $this->assertSame(0, Block::query()->count());
    }

    #[Test]
    public function the_command_fails_on_a_refusal_and_passes_otherwise(): void
    {
        $this->layer('default', $this->hero());
        $this->chain();

        $this->artisan('webx:theme:sync')
            ->expectsOutputToContain('Block types')
            ->expectsOutputToContain('created from webx-ui/default')
            ->assertSuccessful();

        $this->layer('default', $this->hero(['styles' => '.b-hero { color: red; }']));

        $this->artisan('webx:theme:sync')
            ->expectsOutputToContain('1 refused')
            ->assertFailed();
    }

    #[Test]
    public function the_engine_knows_the_same_layout_nodes_as_this_package(): void
    {
        $this->assertSame(Schema::LAYOUT, SchemaCompatibility::LAYOUT);
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

    /**
     * @return list<SyncLine>
     */
    private function sync(bool $force = false, bool $dryRun = false): array
    {
        return $this->app->make(LayerSync::class)->sync($this->chain(), $force, $dryRun);
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

    /**
     * @param  list<SyncLine>  $lines
     */
    private function line(string $subject, array $lines): SyncLine
    {
        foreach ($lines as $line) {
            if ($line->subject === $subject) {
                return $line;
            }
        }

        $this->fail("No line for {$subject}: ".json_encode($lines));
    }

    /**
     * @param  list<SyncLine>  $lines
     */
    private function assertLine(string $subject, string $detail, array $lines): void
    {
        $line = $this->line($subject, $lines);

        $this->assertSame($detail, $line->detail);
        $this->assertFalse($line->refused, $line->detail);
    }
}
