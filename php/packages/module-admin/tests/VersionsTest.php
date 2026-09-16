<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Tests\Fixtures\Article;
use WebxUi\Admin\Versions\EntityVersion;

final class VersionsTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->draft();
            $table->timestamps();
        });
    }

    #[Test]
    public function the_site_reads_the_columns_and_the_preview_reads_the_draft(): void
    {
        $article = Article::query()->create(['slug' => 'hello', 'title' => ['en' => 'Hello'], 'body' => ['blocks' => 1]]);

        $this->assertFalse($article->hasDraft());
        $this->assertFalse($article->isPublished());

        $article->saveDraft(['title' => ['en' => 'Hello there', 'ru' => 'Привет'], 'body' => ['blocks' => 2]]);

        $article->refresh();
        $this->assertTrue($article->hasDraft());
        $this->assertSame('Hello', $article->title, 'the columns are what the site shows');
        $this->assertSame(['blocks' => 1], $article->body);

        $preview = $article->withDraft();
        $this->assertSame('Hello there', $preview->title);
        $this->assertSame(['blocks' => 2], $preview->body);
        $this->assertSame(['en' => 'Hello there', 'ru' => 'Привет'], $preview->getTranslations('title'));
        $this->assertSame($article->getKey(), $preview->getKey());

        // The copy is a copy: the row is untouched.
        $stored = Article::query()->findOrFail($article->getKey());
        $this->assertSame('Hello', $stored->title);
        $this->assertSame(['blocks' => 1], $stored->body);
    }

    #[Test]
    public function publishing_copies_the_draft_into_the_columns_and_writes_the_history(): void
    {
        $article = Article::query()->create(['slug' => 'hello', 'title' => ['en' => 'Hello']]);
        $article->saveDraft(['title' => ['en' => 'First'], 'body' => ['blocks' => 1]]);
        $article->publish(authorId: 7, comment: 'first cut');

        $article->refresh();
        $this->assertSame('First', $article->title);
        $this->assertSame(['blocks' => 1], $article->body);
        $this->assertFalse($article->hasDraft());
        $this->assertTrue($article->isPublished());

        $version = $article->publishedVersions()->first();
        $this->assertNotNull($version);
        $this->assertSame([1, 'published', 7, 'panel', 'first cut'], [$version->number, $version->kind, $version->author_id, $version->source, $version->comment]);
        $this->assertSame(['title' => ['en' => 'First'], 'body' => ['blocks' => 1]], $version->payload, 'the slug is structure, not content');
        $this->assertSame(0, $article->versions()->autosaves()->count(), 'the autosaves insured a draft that no longer exists');

        $article->saveDraft(['title' => ['en' => 'Second'], 'body' => ['blocks' => 2]]);
        $article->publish(source: EntityVersion::SOURCE_MCP);

        $this->assertSame([2, 1], $article->publishedVersions()->pluck('number')->all());
        $this->assertSame('mcp', $article->publishedVersions()->first()?->source);

        $article->unpublish();
        $this->assertFalse($article->refresh()->isPublished());
        $this->assertSame('Second', $article->title, 'the columns stay; only the stamp goes');
    }

    #[Test]
    public function a_never_published_record_publishes_by_the_same_rule(): void
    {
        $article = Article::query()->create(['slug' => 'new']);
        $article->saveDraft(['title' => ['en' => 'Fresh'], 'body' => ['blocks' => 3]]);

        $this->assertNull($article->title);

        $article->publish();

        $this->assertSame('Fresh', $article->refresh()->title);
        $this->assertSame(1, $article->publishedVersions()->count());
    }

    #[Test]
    public function saving_a_draft_keeps_a_ring_of_autosaves_beside_the_history(): void
    {
        $this->app['config']->set('webx-admin.versions.autosaves', 3);

        $article = Article::query()->create(['slug' => 'ring']);

        foreach (range(1, 5) as $step) {
            $article->saveDraft(['body' => ['step' => $step]]);
        }

        $autosaves = $article->versions()->autosaves()->get();
        $this->assertSame([5, 4, 3], $autosaves->map(static fn (EntityVersion $v): mixed => $v->payload['body']['step'])->all());
        $this->assertTrue($autosaves->every(static fn (EntityVersion $v): bool => $v->number === null));
        $this->assertSame(0, $article->publishedVersions()->count(), 'an autosave is not history');

        // Clearing the draft writes no autosave of nothing.
        $article->saveDraft([]);
        $this->assertFalse($article->refresh()->hasDraft());
        $this->assertSame(3, $article->versions()->autosaves()->count());

        $article->discardDraft();
        $this->assertNull($article->refresh()->draft);
    }

    #[Test]
    public function the_history_is_trimmed_to_the_limit_and_a_pinned_version_stays(): void
    {
        $this->app['config']->set('webx-admin.versions.limit', 3);

        $article = Article::query()->create(['slug' => 'limit']);

        foreach (range(1, 5) as $step) {
            $article->saveDraft(['body' => ['step' => $step]]);
            $article->publish();

            if ($step === 1) {
                $article->publishedVersions()->first()?->pin();
            }
        }

        $this->assertSame([5, 4, 3, 1], $article->publishedVersions()->pluck('number')->all());

        // A pinned version is outside the count: three unpinned ones stay next to it.
        $this->assertTrue($article->publishedVersions()->where('number', 1)->first()?->is_pinned);

        // Lowering the limit afterwards leaves rows above it until the command runs.
        $this->app['config']->set('webx-admin.versions.limit', 1);
        $this->assertSame([5, 4, 3, 1], $article->publishedVersions()->pluck('number')->all());

        $this->artisan('webx:versions:prune')
            ->expectsOutputToContain('Removed 2 versions')
            ->assertExitCode(0);

        $this->assertSame([5, 1], $article->publishedVersions()->pluck('number')->all());
    }

    #[Test]
    public function rolling_back_puts_an_old_version_into_the_draft(): void
    {
        $article = Article::query()->create(['slug' => 'back']);

        foreach (['one', 'two', 'three'] as $title) {
            $article->saveDraft(['title' => ['en' => $title]]);
            $article->publish();
        }

        $restored = $article->restoreVersion(1);

        $this->assertSame(1, $restored->number);
        $article->refresh();
        $this->assertSame('three', $article->title, 'the site is untouched until the draft is published');
        $this->assertSame(['title' => ['en' => 'one']], $article->draftValues());

        $article->publish();
        $this->assertSame('one', $article->refresh()->title);
        $this->assertSame([4, 3, 2, 1], $article->publishedVersions()->pluck('number')->all(), 'a rollback is a new publication, not a rewrite');

        $this->expectException(LogicException::class);
        $article->restoreVersion(99);
    }

    #[Test]
    public function a_version_of_another_entity_cannot_be_restored_here(): void
    {
        $one = Article::query()->create(['slug' => 'one']);
        $two = Article::query()->create(['slug' => 'two']);
        $one->saveDraft(['title' => ['en' => 'One']]);
        $one->publish();

        $this->expectException(LogicException::class);

        $two->restoreVersion($one->publishedVersions()->firstOrFail());
    }
}
