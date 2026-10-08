<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

/**
 * A usage source that can also move what it keeps from one key to another.
 *
 * A key changes in one place only — a picture converted to WebP gets `<uuid>.webp` for
 * `<uuid>.jpg` — and every value that names the old key has to name the new one before the old
 * bytes go. The basename is a uuid with an extension, so replacing it as a string is exact: no
 * other value contains it, and the escaped slashes of JSON are not part of it.
 *
 * Tag the class with {@see MediaUsage::TAG}, as for {@see UsageSource}; a source that implements
 * this as well takes part in a rewrite. It is called inside the transaction that changes the
 * file's row, so throwing undoes the conversion of that file.
 */
interface UsageRewriter
{
    /**
     * Replace each file's old basename with its new one everywhere this source keeps it — all
     * of it, not a sample: a rewrite that stops at fifty rows leaves the fifty-first pointing at
     * bytes that are gone.
     *
     * @param  array<int, array{0: string, 1: string}>  $renames  file id → [old basename, new basename]
     * @return array<int, int> file id → places rewritten, or that would be on a dry run
     */
    public function rewrite(array $renames, bool $dryRun = false): array;
}
