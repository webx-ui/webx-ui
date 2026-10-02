<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;
use WebxUi\Blocks\Audit\RegionContentSource;
use WebxUi\Blocks\Models\Region;

/** What the site audit reads of a region: the published tree and the draft over it. */
class AuditSourceTest extends RegionTestCase
{
    #[Test]
    public function the_published_tree_and_the_draft_are_both_read(): void
    {
        $region = $this->region('header', [['key' => 'h1', 'type' => 'bar', 'values' => ['href' => 'https://dev.shop.test/']]]);
        $region->saveDraft(['blocks' => [['key' => 'h1', 'type' => 'bar', 'values' => ['href' => 'http://192.168.0.5/']]]]);

        $source = $this->app->make(RegionContentSource::class);
        $records = iterator_to_array($source->records(), false);

        $this->assertCount(1, $records);
        $this->assertSame('header', $records[0]->id);
        $this->assertTrue($records[0]->published);
        $this->assertSame('/regions/header', $records[0]->editUrl);

        $fields = collect(iterator_to_array($source->fields($records[0]), false))->keyBy('name');

        $this->assertStringContainsString('https://dev.shop.test/', $fields['blocks']->value);
        $this->assertStringContainsString('http://192.168.0.5/', $fields['draft.blocks']->value);
        $this->assertFalse($fields['draft.blocks']->published);
    }

    #[Test]
    public function a_replacement_in_the_draft_keeps_the_rest_of_it(): void
    {
        $region = $this->region('footer', [['key' => 'f1', 'type' => 'bar', 'values' => ['href' => '/']]], published: false);

        $source = $this->app->make(RegionContentSource::class);
        /** @var ContentRecord $record */
        $record = iterator_to_array($source->records(), false)[0];

        $source->replace($record, new ContentField('draft.blocks', '', published: false), ContentField::json([['key' => 'f1', 'type' => 'bar', 'values' => ['href' => '/contacts']]]));

        $this->assertSame('/contacts', Region::query()->findOrFail($region->getKey())->draftValues()['blocks'][0]['values']['href'] ?? null);
    }
}
