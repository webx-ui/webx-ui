<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Support\Carbon;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Rendering\Calls;

/**
 * A block type as a file (§17): the row's fields and one version's content, flat, in the
 * order a reader wants them. This is the answer to the one real cost of keeping types in the
 * database — a block does not travel through git on its own — so the file is made to be
 * read in a diff: settings first, the template and styles as they are, the sample last.
 *
 * The panel moves several types at once, so it writes a pack: the same documents in a list,
 * under a marker that says what the file is. A block travels with the components it calls —
 * without them it would draw nothing on the site it lands on.
 */
final class Exchange
{
    /** The keys that identify and constrain a type, in file order. */
    public const ROW = ['slug', 'kind', 'title', 'description', 'icon', 'group', 'sort', 'allow', 'allowed_in', 'max_per_entity', 'is_enabled'];

    /** What a pack says it is, so that any other JSON is told apart from one. */
    public const FORMAT = 'webx-blocks';

    /** Bumped only when an old reader would misread a new pack. */
    public const FORMAT_VERSION = 1;

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

    /**
     * The types named and every type they call, as documents in a pack — what is called first.
     *
     * The published version goes, as with the command; `$draft` takes the version being edited
     * where there is one. A type with nothing to give is listed in `skipped`, and a slug that is
     * not a type at all — asked for, or called by a template — in `missing`: the pack is still
     * written, and the person who asked is told what it lacks.
     *
     * @param  list<string>  $slugs  Every type when empty.
     * @return array{pack: array<string, mixed>, skipped: list<string>, missing: list<string>}
     */
    public static function pack(array $slugs, bool $draft = false): array
    {
        $all = Block::query()->with(['draftVersion', 'publishedVersion'])->orderBy('slug')->get()->keyBy('slug');

        $queue = $slugs === [] ? $all->keys()->map(static fn ($slug): string => (string) $slug)->all() : $slugs;
        /** @var array<string, BlockVersion> $chosen */
        $chosen = [];
        $skipped = [];
        $missing = [];

        while ($queue !== []) {
            $slug = array_shift($queue);

            if (isset($chosen[$slug]) || in_array($slug, $skipped, true) || in_array($slug, $missing, true)) {
                continue;
            }

            $block = $all->get($slug);

            if (! $block instanceof Block) {
                $missing[] = $slug;

                continue;
            }

            $version = $draft ? $block->currentVersion() : $block->publishedVersion;

            if ($version === null) {
                $skipped[] = $slug;

                continue;
            }

            $chosen[$slug] = $version;
            array_push($queue, ...Calls::of((string) ($version->content()['template'] ?? '')));
        }

        // A circle cannot be published, so a pack of published versions has none; a pack of
        // drafts may, and then it keeps the alphabetical order — the import refuses it anyway.
        try {
            $order = Graph::order(array_map(
                static fn (BlockVersion $version): array => Calls::of((string) ($version->content()['template'] ?? '')),
                $chosen,
            ));
        } catch (CallCycle) {
            $order = array_keys($chosen);
            sort($order);
        }

        $blocks = [];

        foreach ($order as $slug) {
            /** @var Block $block */
            $block = $all->get($slug);
            $blocks[] = self::document($block, $chosen[$slug]);
        }

        return [
            'pack' => [
                'format' => self::FORMAT,
                'format_version' => self::FORMAT_VERSION,
                'exported_at' => Carbon::now()->toAtomString(),
                'blocks' => $blocks,
            ],
            'skipped' => $skipped,
            'missing' => $missing,
        ];
    }

    /**
     * The documents in what was read from a file: a pack, a bare list of documents, or the one
     * document the command writes per type. `null` when it is none of these.
     *
     * Each comes back with its slug and kind settled the way the command always settled them —
     * the file's name stands in for a missing slug, and a file from before kinds held a block.
     *
     * @return list<array{name: string, document: array<string, mixed>}>|null
     */
    public static function read(mixed $decoded, string $name): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        $single = ! array_is_list($decoded) && ! array_key_exists('blocks', $decoded);
        $list = match (true) {
            $single => [$decoded],
            array_is_list($decoded) => $decoded,
            default => $decoded['blocks'],
        };

        if (! is_array($list) || ! array_is_list($list)) {
            return null;
        }

        $documents = [];

        foreach ($list as $document) {
            if (! is_array($document) || array_is_list($document)) {
                return null;
            }

            $slug = is_string($document['slug'] ?? null) ? $document['slug'] : null;

            if ($slug === null && ! $single) {
                return null;
            }

            /** @var array<string, mixed> $document */
            $document['slug'] = $slug ?? basename($name, '.json');
            $document['kind'] ??= Block::KIND_BLOCK;

            $documents[] = ['name' => $name, 'document' => $document];
        }

        return $documents;
    }
}
