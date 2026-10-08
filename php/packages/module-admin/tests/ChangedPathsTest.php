<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebxUi\Admin\Editing\ChangedPaths;

/**
 * The places two copies of a draft differ, named the way the panel's merge names them, so that
 * «Hero › Eyebrow · EN» can be drawn from it rather than «blocks».
 */
final class ChangedPathsTest extends TestCase
{
    #[Test]
    public function a_field_of_one_block_is_named_by_the_block_and_the_field(): void
    {
        $before = ['title' => ['en' => 'About'], 'blocks' => [
            ['key' => 'k1', 'type' => 'hero', 'values' => ['eyebrow' => ['en' => 'Hi']]],
            ['key' => 'k2', 'type' => 'text', 'values' => ['body' => ['en' => 'Body']]],
        ]];
        $after = $before;
        $after['blocks'][0]['values']['eyebrow']['en'] = 'Hello';

        $this->assertSame(
            [[['field' => 'blocks'], ['block' => 'k1', 'type' => 'hero'], ['field' => 'values'], ['field' => 'eyebrow'], ['field' => 'en']]],
            ChangedPaths::between($before, $after),
        );
    }

    #[Test]
    public function an_added_block_and_a_new_order_are_both_said(): void
    {
        $one = ['key' => 'k1', 'type' => 'hero', 'values' => []];
        $two = ['key' => 'k2', 'type' => 'text', 'values' => []];

        $this->assertSame([[['field' => 'blocks'], ['block' => 'k2', 'type' => 'text']]], ChangedPaths::between(['blocks' => [$one]], ['blocks' => [$one, $two]]));
        $this->assertSame([[['field' => 'blocks']]], ChangedPaths::between(['blocks' => [$one, $two]], ['blocks' => [$two, $one]]));
    }

    #[Test]
    public function the_same_value_written_differently_is_no_change(): void
    {
        $this->assertSame([], ChangedPaths::between(['price' => '12', 'note' => ''], ['price' => 12, 'note' => null]));
    }
}
