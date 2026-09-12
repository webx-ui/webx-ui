<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\NestedSet\Exceptions\NestedSetException;
use WebxUi\NestedSet\Tests\Fixtures\Category;
use WebxUi\NestedSet\Tests\Fixtures\Page;

final class NestedSetTest extends TestCase
{
    #[Test]
    public function a_new_node_lands_at_the_end_of_the_roots(): void
    {
        Category::create(['name' => 'first']);
        Category::create(['name' => 'second']);

        $this->assertSame(['first 1-2 d0', 'second 3-4 d0'], $this->layout());
    }

    #[Test]
    public function children_are_appended_and_prepended_inside_the_parent(): void
    {
        $root = Category::create(['name' => 'root']);

        $this->node('a')->appendTo($root);
        $this->node('b')->appendTo($root);
        $this->node('c')->prependTo($root);

        $this->assertSame([
            'root 1-8 d0',
            'c 2-3 d1',
            'a 4-5 d1',
            'b 6-7 d1',
        ], $this->layout());

        $this->assertSame([], Category::checkTreeIntegrity());
    }

    #[Test]
    public function siblings_are_ordered_with_insert_before_and_insert_after(): void
    {
        $root = Category::create(['name' => 'root']);
        $a = $this->node('a');
        $a->appendTo($root);

        $this->node('b')->insertBefore($a);
        $this->node('c')->insertAfter($a);

        $this->assertSame([
            'root 1-8 d0',
            'b 2-3 d1',
            'a 4-5 d1',
            'c 6-7 d1',
        ], $this->layout());
    }

    #[Test]
    public function a_subtree_keeps_its_shape_when_it_moves_to_the_right(): void
    {
        [$root, $a, $a1, $b] = $this->sampleTree();

        $a->appendTo($b);

        $this->assertSame([
            'root 1-8 d0',
            'b 2-7 d1',
            'a 3-6 d2',
            'a1 4-5 d3',
        ], $this->layout());

        $this->assertSame($b->getKey(), $a->fresh()?->parent_id);
        $this->assertSame([], Category::checkTreeIntegrity());
        $this->assertTrue($a1->fresh()?->isDescendantOf($b->fresh()));
        unset($root);
    }

    #[Test]
    public function a_subtree_keeps_its_shape_when_it_moves_to_the_left(): void
    {
        [$root, $a, $a1, $b] = $this->sampleTree();

        $b->insertBefore($a);

        $this->assertSame([
            'root 1-8 d0',
            'b 2-3 d1',
            'a 4-7 d1',
            'a1 5-6 d2',
        ], $this->layout());

        $this->assertSame([], Category::checkTreeIntegrity());
        unset($root, $a1);
    }

    #[Test]
    public function a_node_can_be_lifted_out_to_the_root_level(): void
    {
        [$root, $a, $a1, $b] = $this->sampleTree();

        $a->saveAsRoot();

        $this->assertSame([
            'root 1-4 d0',
            'b 2-3 d1',
            'a 5-8 d0',
            'a1 6-7 d1',
        ], $this->layout());

        $this->assertNull($a->fresh()?->parent_id);
        $this->assertSame([], Category::checkTreeIntegrity());
        unset($root, $a1, $b);
    }

    #[Test]
    public function a_node_cannot_be_moved_into_its_own_subtree(): void
    {
        [$root, $a, $a1] = $this->sampleTree();

        $this->expectException(NestedSetException::class);

        $a->appendTo($a1);

        unset($root);
    }

    #[Test]
    public function deleting_a_node_takes_its_subtree_and_closes_the_gap(): void
    {
        [$root, $a] = $this->sampleTree();

        $a->delete();

        $this->assertSame([
            'root 1-4 d0',
            'b 2-3 d1',
        ], $this->layout());

        $this->assertSame([], Category::checkTreeIntegrity());
        unset($root);
    }

    #[Test]
    public function it_reads_ancestors_descendants_and_siblings(): void
    {
        [$root, $a, $a1, $b] = $this->sampleTree();

        $this->assertSame(['root', 'a'], $a1->ancestors()->pluck('name')->all());
        $this->assertSame(['a', 'a1', 'b'], $root->descendants()->pluck('name')->all());
        $this->assertSame(['b'], $a->siblings()->pluck('name')->all());
        $this->assertSame(['root', 'a', 'a1'], $a1->pathFromRoot()->pluck('name')->all());

        $this->assertTrue($root->isRoot());
        $this->assertFalse($a->isRoot());
        $this->assertTrue($a1->isLeaf());
        $this->assertFalse($a->isLeaf());
        $this->assertTrue($a1->isDescendantOf($root));
        $this->assertFalse($b->isDescendantOf($a));
        $this->assertTrue($a1->isChildOf($a));
    }

    #[Test]
    public function up_and_down_swap_a_node_with_its_sibling(): void
    {
        $root = Category::create(['name' => 'root']);
        $a = $this->node('a');
        $a->appendTo($root);
        $b = $this->node('b');
        $b->appendTo($root);

        $this->assertTrue($b->up());
        $this->assertSame(['root 1-6 d0', 'b 2-3 d1', 'a 4-5 d1'], $this->layout());

        $this->assertTrue($b->down());
        $this->assertSame(['root 1-6 d0', 'a 2-3 d1', 'b 4-5 d1'], $this->layout());

        $this->assertFalse($b->down());
    }

    #[Test]
    public function the_query_scopes_select_roots_in_tree_order(): void
    {
        $this->sampleTree();
        Category::create(['name' => 'second root']);

        $this->assertSame(
            ['root', 'second root'],
            Category::query()->roots()->ordered()->pluck('name')->all(),
        );

        $this->assertSame(
            ['root', 'a', 'a1', 'b', 'second root'],
            Category::query()->ordered()->pluck('name')->all(),
        );
    }

    #[Test]
    public function a_flat_list_is_nested_into_a_tree(): void
    {
        $this->sampleTree();

        $roots = Category::toTree(Category::query()->ordered()->get());

        $this->assertCount(1, $roots);
        $this->assertSame('root', $roots[0]->name);
        $this->assertSame(['a', 'b'], $roots[0]->children->pluck('name')->all());
        $this->assertSame(['a1'], $roots[0]->children[0]->children->pluck('name')->all());
    }

    #[Test]
    public function fix_tree_rebuilds_bounds_from_parent_id(): void
    {
        $this->sampleTree();

        DB::table('categories')->update(['lft' => 0, 'rgt' => 0, 'depth' => 0]);

        $this->assertNotSame([], Category::checkTreeIntegrity());
        $this->assertSame(4, Category::fixTree());
        $this->assertSame([], Category::checkTreeIntegrity());
        $this->assertSame(0, Category::fixTree());
    }

    #[Test]
    public function trees_in_the_same_table_do_not_touch_each_other(): void
    {
        $one = Page::create(['site_id' => 1, 'name' => 'one']);
        $two = Page::create(['site_id' => 2, 'name' => 'two']);

        (new Page(['name' => 'one-child']))->appendTo($one);
        (new Page(['name' => 'two-child']))->appendTo($two);

        $this->assertSame(['one 1-4 d0', 'one-child 2-3 d1'], $this->layout(Page::class, 1));
        $this->assertSame(['two 1-4 d0', 'two-child 2-3 d1'], $this->layout(Page::class, 2));

        $this->assertSame([], Page::checkTreeIntegrity(['site_id' => 1]));
        $this->assertSame([], Page::checkTreeIntegrity(['site_id' => 2]));
    }

    #[Test]
    public function a_node_cannot_be_moved_into_another_tree(): void
    {
        $one = Page::create(['site_id' => 1, 'name' => 'one']);
        $two = Page::create(['site_id' => 2, 'name' => 'two']);

        $this->expectException(NestedSetException::class);

        $two->appendTo($one);
    }

    /**
     * root ── a ── a1
     *     └── b
     *
     * @return array{Category, Category, Category, Category}
     */
    private function sampleTree(): array
    {
        $root = Category::create(['name' => 'root']);

        $a = $this->node('a');
        $a->appendTo($root);

        $a1 = $this->node('a1');
        $a1->appendTo($a);

        $b = $this->node('b');
        $b->appendTo($root);

        return [$root->refresh(), $a->refresh(), $a1->refresh(), $b->refresh()];
    }

    private function node(string $name): Category
    {
        return new Category(['name' => $name]);
    }

    /**
     * @return list<string>
     */
    private function layout(string $model = Category::class, ?int $siteId = null): array
    {
        $query = $model::query()->orderBy('lft');

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->get()
            ->map(fn ($node): string => sprintf(
                '%s %d-%d d%d',
                $node->name,
                $node->lft,
                $node->rgt,
                $node->depth,
            ))
            ->all();
    }
}
