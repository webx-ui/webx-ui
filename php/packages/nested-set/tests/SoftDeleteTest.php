<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\NestedSet\Exceptions\NestedSetException;
use WebxUi\NestedSet\Tests\Fixtures\Note;
use WebxUi\NestedSet\Tests\Fixtures\StrictNote;

class SoftDeleteTest extends TestCase
{
    #[Test]
    public function a_model_that_says_nothing_is_refused_the_delete(): void
    {
        $note = StrictNote::query()->create(['name' => 'Root']);

        $this->expectException(NestedSetException::class);

        $note->delete();
    }

    #[Test]
    public function a_trashed_node_keeps_its_place(): void
    {
        $root = Note::query()->create(['name' => 'Root']);
        $child = new Note(['name' => 'Child']);
        $child->appendTo($root);

        $bounds = [$child->getLft(), $child->getRgt()];

        $child->delete();

        $trashed = Note::withTrashed()->whereKey($child->getKey())->firstOrFail();

        $this->assertSame($bounds, [$trashed->getLft(), $trashed->getRgt()]);
        $this->assertSame($root->getKey(), $trashed->parent_id);
        $this->assertSame([], Note::checkTreeIntegrity(), 'the bounds still add up');
    }

    #[Test]
    public function the_descendants_of_a_trashed_node_are_left_to_the_model(): void
    {
        $root = Note::query()->create(['name' => 'Root']);
        $child = new Note(['name' => 'Child']);
        $child->appendTo($root);
        $grandchild = new Note(['name' => 'Grandchild']);
        $grandchild->appendTo($child);

        $child->delete();

        // Nothing has happened to the one below it: a branch is the model's idea of a delete,
        // not the tree's.
        $this->assertNotNull(Note::query()->find($grandchild->getKey()));
    }

    #[Test]
    public function a_restored_node_is_back_where_it_was(): void
    {
        $root = Note::query()->create(['name' => 'Root']);
        $first = new Note(['name' => 'First']);
        $first->appendTo($root);
        $second = new Note(['name' => 'Second']);
        $second->appendTo($root);

        $first->delete();
        Note::withTrashed()->findOrFail($first->getKey())->restore();

        $this->assertSame(
            ['Root', 'First', 'Second'],
            Note::query()->ordered()->pluck('name')->all(),
        );
    }

    #[Test]
    public function a_force_delete_still_takes_the_branch_and_closes_the_gap(): void
    {
        $root = Note::query()->create(['name' => 'Root']);
        $child = new Note(['name' => 'Child']);
        $child->appendTo($root);
        $grandchild = new Note(['name' => 'Grandchild']);
        $grandchild->appendTo($child);

        $child->forceDelete();

        $this->assertSame(0, Note::withTrashed()->whereKey([$child->getKey(), $grandchild->getKey()])->count());
        $this->assertSame([], Note::checkTreeIntegrity());
        $this->assertSame(2, Note::withTrashed()->findOrFail($root->getKey())->getRgt());
    }
}
