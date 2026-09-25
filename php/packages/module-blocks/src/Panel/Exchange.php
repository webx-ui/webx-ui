<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Support\Carbon;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;

/**
 * A block type as a file (§17): the row's fields and one version's content, flat, in the
 * order a reader wants them. This is the answer to the one real cost of keeping types in the
 * database — a block does not travel through git on its own — so the file is made to be
 * read in a diff: settings first, the template and styles as they are, the sample last.
 */
final class Exchange
{
    /** The keys that identify and constrain a type, in file order. */
    public const ROW = ['slug', 'kind', 'title', 'description', 'icon', 'group', 'sort', 'allow', 'allowed_in', 'max_per_entity', 'is_enabled'];

    /**
     * @return array<string, mixed>
     */
    public static function document(Block $block, BlockVersion $version): array
    {
        $document = [];

        foreach (self::ROW as $key) {
            $document[$key] = $block->getAttribute($key);
        }

        $document['version'] = $version->number;
        $document['exported_at'] = Carbon::now()->toAtomString();

        return $document + $version->content();
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function encode(array $document): string
    {
        return json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
