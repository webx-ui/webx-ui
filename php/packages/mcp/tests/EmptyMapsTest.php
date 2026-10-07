<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use PHPUnit\Framework\Attributes\Test;
use stdClass;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Mcp\Server\EmptyMaps;

final class EmptyMapsTest extends TestCase
{
    #[Test]
    public function an_empty_language_map_goes_out_as_an_object_and_a_list_stays_a_list(): void
    {
        $this->app->make(ScreenRegistry::class)->register('things.form', [
            ['id' => 'lead', 'type' => 'wx-textarea', 'name' => 'lead', 'label' => 'Lead', 'localized' => true],
            ['id' => 'points', 'type' => 'wx-collection', 'name' => 'points', 'label' => 'Points', 'localized' => true],
            ['id' => 'code', 'type' => 'wx-input', 'name' => 'code', 'label' => 'Code'],
        ]);

        $schema = ['type' => 'object', 'properties' => [
            'title' => ['type' => ['string', 'object']],
            'tags' => ['type' => 'array'],
            'values' => ['type' => 'object', 'properties' => ['note' => ['type' => ['object', 'null']]]],
        ]];

        $answer = $this->app->make(EmptyMaps::class)->apply([
            'items' => [
                ['title' => [], 'lead' => [], 'seo' => [], 'tags' => [], 'points' => [], 'code' => []],
                ['title' => ['en' => 'Two'], 'lead' => ['en' => 'Said'], 'values' => ['note' => []]],
            ],
            'unrelated' => [],
        ], $schema);

        $first = $answer['items'][0];

        foreach (['title', 'lead', 'seo'] as $map) {
            $this->assertInstanceOf(stdClass::class, $first[$map], $map);
        }

        foreach (['tags', 'points', 'code'] as $list) {
            $this->assertSame([], $first[$list], $list);
        }

        $this->assertSame(['en' => 'Two'], $answer['items'][1]['title']);
        $this->assertInstanceOf(stdClass::class, $answer['items'][1]['values']['note']);
        $this->assertSame([], $answer['unrelated']);
        $this->assertSame('{"title":{},"lead":{},"seo":{},"tags":[],"points":[],"code":[]}', json_encode($first));
    }
}
