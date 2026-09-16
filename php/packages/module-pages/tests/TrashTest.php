<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\Models\Route;

class TrashTest extends TestCase
{
    #[Test]
    public function deleting_a_page_puts_its_whole_branch_in_the_bin(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $red = $this->page('red', $shoes);

        $catalog->delete();

        $this->assertSame(0, Page::query()->whereKey([$catalog->id, $shoes->id, $red->id])->count());
        $this->assertSame(3, Page::onlyTrashed()->count());

        // The node that was actually deleted answers to nobody; the two below it name it.
        $this->assertNull(Page::withTrashed()->find($catalog->id)?->trashed_with);
        $this->assertSame($catalog->id, Page::withTrashed()->find($shoes->id)?->trashed_with);
        $this->assertSame($catalog->id, Page::withTrashed()->find($red->id)?->trashed_with);
    }

    #[Test]
    public function every_address_in_the_branch_is_released(): void
    {
        $catalog = $this->page('catalog');
        $this->page('shoes', $catalog);

        $catalog->delete();

        $this->assertSame(0, Route::query()->whereIn('path', ['catalog', 'catalog/shoes'])->count());
    }

    #[Test]
    public function the_tree_keeps_its_shape_while_the_branch_is_in_the_bin(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $about = $this->page('about');

        $catalog->delete();

        $this->assertSame([], Page::checkTreeIntegrity());
        $this->assertSame($catalog->id, Page::withTrashed()->find($shoes->id)?->parent_id);
        $this->assertSame('about', $about->refresh()->routePath(), 'a neighbour is untouched');
    }

    #[Test]
    public function a_restore_brings_back_only_what_went_down_together(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $red = $this->page('red', $shoes);

        // Deleted earlier and separately: it must stay in the bin when the branch above it
        // comes back, or every restore would undo every deletion ever made under that node.
        $red->delete();
        $catalog->delete();

        Page::withTrashed()->findOrFail($catalog->id)->restoreBranch();

        $this->assertNotNull(Page::query()->find($catalog->id));
        $this->assertNotNull(Page::query()->find($shoes->id));
        $this->assertNull(Page::query()->find($red->id), 'it was already in the bin on its own');
    }

    #[Test]
    public function a_restored_branch_takes_its_addresses_back(): void
    {
        $catalog = $this->page('catalog');
        $this->page('shoes', $catalog);

        $catalog->delete();
        Page::withTrashed()->findOrFail($catalog->id)->restoreBranch();

        $this->assertSame(
            ['catalog', 'catalog/shoes'],
            Route::query()->whereIn('path', ['catalog', 'catalog/shoes'])->orderBy('path')->pluck('path')->all(),
        );
    }

    #[Test]
    public function an_address_taken_while_the_page_was_in_the_bin_refuses_the_restore(): void
    {
        $about = $this->page('about');
        $about->delete();

        $this->page('about');

        $this->expectException(PathRejected::class);

        Page::withTrashed()->findOrFail($about->id)->restoreBranch();
    }

    #[Test]
    public function a_page_deleted_and_restored_may_be_deleted_again(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);

        $catalog->delete();
        Page::withTrashed()->findOrFail($catalog->id)->restoreBranch();

        // `trashed_with` has to be cleared by the restore, or the second delete would take the
        // node for one already on its way down and leave its children standing.
        Page::query()->findOrFail($catalog->id)->delete();

        $this->assertSame($catalog->id, Page::withTrashed()->find($shoes->id)?->trashed_with);
    }
}
