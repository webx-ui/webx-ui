<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Media\Models\MediaFile;

/**
 * One way of finding where library files are in use.
 *
 * The library cannot know every place a site keeps a file — a cover is a foreign key, a block
 * stores `{ path, alt }` inside JSON, a redirect points at `/storage/…` — so the answer is put
 * together from sources. {@see DatabaseUsage} is the one that ships and covers what can be found
 * by looking at the schema; a module that keeps files somewhere it cannot see (another database,
 * a remote service, a value it hashes) tags its own source with {@see MediaUsage::TAG}.
 */
interface UsageSource
{
    /**
     * @param  Collection<int, MediaFile>  $files
     * @return iterable<int, Place>
     */
    public function find(Collection $files): iterable;
}
