<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;
use WebxUi\Pages\Audit\PageContentSource;
use WebxUi\Pages\Models\Page;

/** What the site audit reads of a page: the published blocks, the draft, nothing from the bin. */
class AuditSourceTest extends TestCase
{
    #[Test]
    public function the_blocks_and_the_draft_are_both_read(): void
    {
        $page = $this->page('delivery');
        $page->setAttribute('blocks', [['type' => 'text', 'values' => ['body' => '<a href="https://dev.shop.test/sale">Sale</a>']]]);
        $page->save();
        $page->saveDraft(['title' => ['en' => 'Delivery'], 'blocks' => [['type' => 'image', 'values' => ['src' => 'http://localhost/a.jpg']]]]);

        $this->page('hidden', published: false)->delete();

        $source = new PageContentSource;
        $records = iterator_to_array($source->records(), false);

        $this->assertNotContains('hidden', array_map(static fn (ContentRecord $record): string => $record->label, $records));

        $record = collect($records)->firstWhere('label', 'Delivery');
        $this->assertInstanceOf(ContentRecord::class, $record);
        $this->assertTrue($record->published);
        $this->assertSame('/pages/'.$page->getKey(), $record->editUrl);

        $fields = collect(iterator_to_array($source->fields($record), false))->keyBy('name');

        $this->assertStringContainsString('https://dev.shop.test/sale', $fields['blocks']->value);
        $this->assertNull($fields['blocks']->published, 'Published as the page is.');
        $this->assertStringContainsString('http://localhost/a.jpg', $fields['draft.blocks']->value);
        $this->assertFalse($fields['draft.blocks']->published);
    }

    #[Test]
    public function a_replacement_is_written_through_the_model(): void
    {
        $page = $this->page('delivery');
        $page->saveDraft(['blocks' => [['type' => 'image', 'values' => ['src' => 'http://localhost/a.jpg']]]]);

        $source = new PageContentSource;
        $record = collect(iterator_to_array($source->records(), false))->firstWhere('id', (string) $page->getKey());
        $this->assertInstanceOf(ContentRecord::class, $record);

        $field = new ContentField('draft.blocks', ContentField::json([['type' => 'image', 'values' => ['src' => 'http://localhost/a.jpg']]]), published: false);
        $source->replace($record, $field, str_replace('http://localhost', '', $field->value));

        $draft = Page::query()->findOrFail($page->getKey())->draftValues();
        $this->assertSame('/a.jpg', $draft['blocks'][0]['values']['src'] ?? null);
    }
}
