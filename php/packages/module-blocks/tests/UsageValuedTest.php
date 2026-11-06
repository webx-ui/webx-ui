<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Panel\Usage;
use WebxUi\Blocks\Tests\Fixtures\Page;

/**
 * `Usage::valued()`: the places of a type by the value of one of its fields, on the same walk and
 * with the same row as the rest of `Usage`. The trees are written past the validation on purpose:
 * this reads what is stored, whatever a type's schema says today.
 */
final class UsageValuedTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-blocks.entities', [Page::class]);
        $app['config']->set('webx-blocks.regions', ['footer' => ['title' => 'Footer']]);
    }

    #[Test]
    public function it_answers_by_value_with_the_tree_each_stands_in(): void
    {
        $page = Page::query()->create([
            'title' => 'Home',
            'blocks' => [$this->shelf('a'), $this->shelf('b')],
            'draft' => ['blocks' => [$this->shelf('b'), $this->shelf('c')]],
        ]);

        $found = $this->app->make(Usage::class)->valued('shelf', 'name');

        $this->assertSame(['a', 'b', 'c'], array_keys($found));
        $this->assertSame([true, false], [$found['a'][0]['live'], $found['a'][0]['draft']]);
        $this->assertSame([true, true], [$found['b'][0]['live'], $found['b'][0]['draft']]);
        $this->assertSame([false, true], [$found['c'][0]['live'], $found['c'][0]['draft']]);

        $this->assertSame(Page::class, $found['a'][0]['model']);
        $this->assertSame($page->getKey(), $found['a'][0]['id']);
        $this->assertSame('Home', $found['a'][0]['title']);
        $this->assertNull($found['a'][0]['region']);
    }

    #[Test]
    public function an_entity_with_a_value_twice_is_one_place_and_a_nested_block_is_found(): void
    {
        Page::query()->create([
            'title' => 'Home',
            'blocks' => [
                $this->shelf('a'),
                $this->shelf('a'),
                ['key' => 'p', 'type' => 'columns', 'values' => ['items' => [$this->shelf('deep')]]],
            ],
        ]);

        $found = $this->app->make(Usage::class)->valued('shelf', 'name');

        $this->assertCount(1, $found['a']);
        $this->assertCount(1, $found['deep']);
    }

    #[Test]
    public function a_region_is_named_and_what_names_nothing_is_left_out(): void
    {
        (new Region)->forceFill(['name' => 'footer', 'blocks' => [
            $this->shelf('a'),
            $this->shelf(''),
            $this->shelf(null),
            $this->shelf(['en' => 'a']),
            ['key' => 'x', 'type' => 'other', 'values' => ['name' => 'b']],
        ]])->save();

        $found = $this->app->make(Usage::class)->valued('shelf', 'name');

        $this->assertSame(['a'], array_keys($found));
        $this->assertSame('footer', $found['a'][0]['region']);
    }

    #[Test]
    public function a_value_that_reads_as_a_number_is_still_found_by_it(): void
    {
        Page::query()->create(['title' => 'Home', 'blocks' => [$this->shelf('2026')], 'draft' => ['blocks' => [$this->shelf('2026')]]]);

        $found = $this->app->make(Usage::class)->valued('shelf', 'name');

        [$place] = array_values($found)[0];

        $this->assertTrue($place['live']);
        $this->assertTrue($place['draft']);
    }

    /**
     * @return array{key: string, type: string, values: array<string, mixed>}
     */
    private function shelf(mixed $name): array
    {
        return ['key' => 'k'.uniqid(), 'type' => 'shelf', 'values' => ['name' => $name]];
    }
}
