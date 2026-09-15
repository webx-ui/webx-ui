<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\NestedSet\Exceptions\NestedSetException;
use WebxUi\NestedSet\Tests\Fixtures\Category;

/**
 * A node saved without a place in the tree: what "new page" creates before anybody has decided
 * where the page goes.
 */
final class DetachedTest extends TestCase
{
    #[Test]
    public function a_detached_node_exists_without_touching_the_tree(): void
    {
        $root = Category::query()->create(['name' => 'Catalogue']);
        $child = new Category(['name' => 'Phones']);
        $child->appendTo($root);

        $draft = new Category(['name' => 'Draft']);
        $this->assertTrue($draft->saveDetached());

        $this->assertTrue($draft->exists);
        $this->assertTrue($draft->isDetached());
        $this->assertFalse($draft->isRoot());
        $this->assertNull($draft->parent_id);
        $this->assertSame([0, 0, 0], [$draft->getLft(), $draft->getRgt(), $draft->getDepth()]);

        // Nothing else moved.
        $this->assertSame([1, 4], [$root->refresh()->getLft(), $root->getRgt()]);
        $this->assertSame([2, 3], [$child->refresh()->getLft(), $child->getRgt()]);
    }

    #[Test]
    public function the_tree_reads_around_it(): void
    {
        $root = Category::query()->create(['name' => 'Catalogue']);
        $draft = new Category(['name' => 'Draft']);
        $draft->saveDetached();

        $this->assertSame(['Catalogue'], Category::query()->ordered()->pluck('name')->all());
        $this->assertSame(['Catalogue'], Category::query()->roots()->pluck('name')->all());
        $this->assertSame(['Catalogue'], Category::query()->placed()->pluck('name')->all());
        $this->assertSame(['Draft'], Category::query()->detached()->pluck('name')->all());

        $this->assertSame([], $draft->ancestors()->get()->all());
        $this->assertSame([], $draft->descendants()->get()->all());
        $this->assertSame([], $draft->siblings()->get()->all(), 'the roots are not its siblings');
        $this->assertSame(['Draft'], $draft->pathFromRoot()->pluck('name')->all());
        $this->assertFalse($draft->up());
        $this->assertFalse($draft->down());

        $this->assertSame([], Category::checkTreeIntegrity());
        $this->assertSame(0, Category::fixTree());
        $this->assertTrue($draft->refresh()->isDetached(), 'fixTree leaves a detached node where it is');
        $this->assertSame(['Catalogue'], Category::toTree(Category::query()->ordered()->get())->pluck('name')->all());
        $this->assertSame([1, 2], [$root->refresh()->getLft(), $root->getRgt()]);
    }

    #[Test]
    public function a_row_with_a_parent_and_no_bounds_is_damage_not_a_draft(): void
    {
        $root = Category::query()->create(['name' => 'Catalogue']);
        $draft = new Category(['name' => 'Draft']);
        $draft->saveDetached();

        // What an import that wrote parent_id and nothing else leaves behind.
        $orphan = Category::query()->create(['name' => 'Imported']);
        DB::table('categories')->where('id', $orphan->getKey())->update(['lft' => 0, 'rgt' => 0, 'parent_id' => $root->getKey()]);
        $orphan->refresh();

        $this->assertFalse($orphan->isDetached());
        $this->assertNotSame([], Category::checkTreeIntegrity());
        $this->assertSame(2, Category::fixTree(), 'the orphan and the root that grew around it');
        $this->assertSame([], Category::checkTreeIntegrity());
        $this->assertSame([2, 3], [$orphan->refresh()->getLft(), $orphan->getRgt()]);
        $this->assertTrue($draft->refresh()->isDetached());
    }

    #[Test]
    public function placing_it_is_an_insert_not_a_move(): void
    {
        $root = Category::query()->create(['name' => 'Catalogue']);
        $first = new Category(['name' => 'Phones']);
        $first->appendTo($root);

        $draft = new Category(['name' => 'Draft']);
        $draft->saveDetached();

        $moved = 0;
        Event::listen('eloquent.moved: '.Category::class, static function () use (&$moved): void {
            $moved++;
        });

        $this->assertTrue($draft->appendTo($root));

        $draft->refresh();
        $this->assertFalse($draft->isDetached());
        $this->assertSame($root->getKey(), $draft->parent_id);
        $this->assertSame([4, 5, 1], [$draft->getLft(), $draft->getRgt(), $draft->getDepth()]);
        $this->assertSame([1, 6], [$root->refresh()->getLft(), $root->getRgt()]);
        $this->assertSame(['Phones', 'Draft'], $root->children()->pluck('name')->all());
        $this->assertSame([], Category::checkTreeIntegrity());
        $this->assertSame(0, $moved, 'a first placement is a save, and `updated` already tells');

        // Placed once, it moves like any other node from then on.
        $this->assertTrue($draft->saveAsRoot());
        $this->assertSame(1, $moved);
        $this->assertSame([], Category::checkTreeIntegrity());
        $this->assertSame(['Catalogue', 'Draft'], Category::query()->roots()->ordered()->pluck('name')->all());
    }

    #[Test]
    public function it_can_become_a_root_too(): void
    {
        Category::query()->create(['name' => 'Catalogue']);
        $draft = new Category(['name' => 'Draft']);
        $draft->saveDetached();

        $this->assertTrue($draft->saveAsRoot());
        $this->assertSame([3, 4], [$draft->refresh()->getLft(), $draft->getRgt()]);
        $this->assertTrue($draft->isRoot());
        $this->assertSame([], Category::checkTreeIntegrity());
    }

    #[Test]
    public function deleting_it_closes_no_gap(): void
    {
        $root = Category::query()->create(['name' => 'Catalogue']);
        $draft = new Category(['name' => 'Draft']);
        $draft->saveDetached();

        $draft->delete();

        $this->assertSame([1, 2], [$root->refresh()->getLft(), $root->getRgt()]);
        $this->assertSame([], Category::checkTreeIntegrity());
        $this->assertSame(0, Category::query()->detached()->count());
    }

    #[Test]
    public function nothing_can_be_placed_relative_to_it(): void
    {
        $draft = new Category(['name' => 'Draft']);
        $draft->saveDetached();

        $this->expectException(NestedSetException::class);
        $this->expectExceptionMessage('detached');

        (new Category(['name' => 'Child']))->appendTo($draft);
    }

    #[Test]
    public function a_placed_node_cannot_be_detached(): void
    {
        $root = Category::query()->create(['name' => 'Catalogue']);

        $this->expectException(NestedSetException::class);
        $this->expectExceptionMessage('already has a place');

        $root->saveDetached();
    }
}
