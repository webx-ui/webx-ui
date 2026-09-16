<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Pages\Models\Page;

class DraftTest extends TestCase
{
    #[Test]
    public function a_new_page_has_never_been_published(): void
    {
        $page = $this->page('about', published: false);

        $this->assertSame(Page::STATUS_DRAFT, $page->status());
    }

    #[Test]
    public function an_autosave_does_not_reach_the_site(): void
    {
        $page = $this->page('about');
        $page->saveDraft(['title' => ['en' => 'About us']]);

        $this->assertSame('About', $page->refresh()->title, 'the columns are what the site shows');
        $this->assertSame(Page::STATUS_MODIFIED, $page->status());
        $this->assertSame('About us', $page->withDraft()->title);
    }

    #[Test]
    public function publishing_moves_the_draft_into_the_columns_and_clears_the_autosaves(): void
    {
        $page = $this->page('about', published: false);
        $page->saveDraft(['title' => ['en' => 'About us']]);
        $page->saveDraft(['title' => ['en' => 'About us, really']]);

        $this->assertSame(2, $page->versions()->autosaves()->count());

        $page->publish();

        $this->assertSame('About us, really', $page->refresh()->title);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status());
        $this->assertSame(0, $page->versions()->autosaves()->count());
        $this->assertSame(1, $page->publishedVersions()->count());
    }

    #[Test]
    public function restoring_a_version_puts_it_into_the_draft_and_not_onto_the_site(): void
    {
        $page = $this->page('about', published: false);
        $page->saveDraft(['title' => ['en' => 'First']]);
        $page->publish();

        $page->saveDraft(['title' => ['en' => 'Second']]);
        $page->publish();

        $page->restoreVersion(1);

        $this->assertSame('Second', $page->refresh()->title, 'the site still shows what was published');
        $this->assertSame('First', $page->withDraft()->title);
        $this->assertSame(Page::STATUS_MODIFIED, $page->status());
    }

    #[Test]
    public function a_version_carries_the_content_and_not_the_place_in_the_tree(): void
    {
        $page = $this->page('about', published: false);
        $page->publish();

        $payload = $page->publishedVersions()->firstOrFail()->payload;

        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('blocks', $payload);

        foreach (['slug', 'lft', 'rgt', 'depth', 'parent_id', 'draft', 'published_at', 'trashed_with'] as $structural) {
            $this->assertArrayNotHasKey($structural, $payload);
        }
    }

    #[Test]
    public function unpublishing_takes_the_page_off_the_site_and_leaves_the_content(): void
    {
        $page = $this->page('about');
        $page->unpublish();

        $this->assertSame(Page::STATUS_DRAFT, $page->status());
        $this->assertSame('About', $page->refresh()->title);
    }

    #[Test]
    public function who_made_a_version_and_where_from_is_kept(): void
    {
        $page = $this->page('about');
        $page->saveDraft(['title' => ['en' => 'By an agent']], authorId: null, source: EntityVersion::SOURCE_MCP);
        $page->publish(source: EntityVersion::SOURCE_MCP);

        $this->assertSame(EntityVersion::SOURCE_MCP, $page->publishedVersions()->firstOrFail()->source);
    }
}
