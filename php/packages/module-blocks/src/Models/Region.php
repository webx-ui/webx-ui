<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Blocks\HasBlocks;
use WebxUi\Blocks\Regions;

/**
 * A region of the layout: one place with a tree of blocks — the header, the footer.
 *
 * The same three traits a page has, and nothing else, because a region is edited the way a
 * page is: a draft, a publication, a history to roll back to. What it has not got is words of
 * its own — the title lives in `webx-blocks.regions` beside the name, like a declared menu's —
 * and an address: it is printed by whatever page the visitor is on.
 *
 * @property int $id
 * @property string $name
 * @property list<array<string, mixed>>|null $blocks
 * @property array<string, mixed>|null $draft
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Region extends Model
{
    use HasBlocks;
    use HasDraft;
    use HasVersions;

    protected $table = 'block_regions';

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        // The site reads the published tree from the cache, so whatever changes it drops the
        // copy: a publication, taking it off, a row removed by `--prune`. A draft saved on every
        // keystroke changes neither column and leaves the cache alone.
        static::saved(static function (Region $region): void {
            if ($region->wasRecentlyCreated || $region->wasChanged(['blocks', 'published_at', 'name'])) {
                Container::getInstance()->make(Regions::class)->forget($region->name);
            }
        });

        static::deleted(static function (Region $region): void {
            Container::getInstance()->make(Regions::class)->forget($region->name);
        });
    }

    /**
     * The name is the region's identity rather than part of its content: restoring an old
     * version must not be able to rename it.
     *
     * @return list<string>
     */
    public function unversionedAttributes(): array
    {
        return ['draft', 'published_at', 'name'];
    }

    /**
     * What an editor is working on: the draft's tree when there is one, what the site shows
     * otherwise — the tree the panel opens and an agent edits.
     *
     * @return list<array<string, mixed>>
     */
    public function editingTree(): array
    {
        $draft = $this->draftValues();

        if (is_array($draft['blocks'] ?? null)) {
            return array_values($draft['blocks']);
        }

        return $this->blocksTree();
    }
}
