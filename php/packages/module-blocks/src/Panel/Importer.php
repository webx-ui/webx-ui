<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Rendering\Calls;
use WebxUi\Blocks\Rendering\Thumbnails;

/**
 * Block types back from their documents (§17) — the one door the command and the panel share.
 *
 * Each document is checked by the rules the panel checks a save with, so a document that
 * imports is a type the panel would have accepted. The row is brought up to date; the content is
 * written as a new version only when it differs from the version being edited — importing the
 * same file twice writes nothing. Publishing, when asked, runs the publish checks on what was
 * written; a type that fails stays a draft and its row says why.
 *
 * The documents are written in the order of the call graph — what is called before what calls
 * it — so that publishing a parent checks it against children that are already there.
 *
 * A dry run that is asked to publish rehearses: everything is written and checked inside a
 * transaction that is rolled back, so the plan says which types the checks would hold back
 * before anything is written.
 */
final class Importer
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const UNCHANGED = 'unchanged';

    public const FAILED = 'failed';

    public function __construct(
        private readonly ValidatorFactory $validator,
        private readonly Publisher $publisher,
        private readonly Usage $usage,
        private readonly BlockTypes $types,
        private readonly Thumbnails $thumbnails,
    ) {}

    /**
     * @param  list<array{name: string, document: array<string, mixed>}>  $documents  As {@see Exchange::read()} gives them.
     * @return list<array{slug: string, title: string|null, kind: string, name: string, status: string, writes: bool, version: int|null, published: int|null, error: string|null}>
     *
     * @throws CallCycle A circle among the documents: refused before anything is written.
     */
    public function import(array $documents, bool $dryRun = false, bool $publish = false): array
    {
        /** @var array<string, array{name: string, document: array<string, mixed>}> $read */
        $read = [];

        // The same slug twice in one file: the later one wins, as a second file would.
        foreach ($documents as $one) {
            $read[(string) $one['document']['slug']] = $one;
        }

        $order = Graph::order(array_map(
            static fn (array $one): array => Calls::of(is_string($one['document']['template'] ?? null) ? $one['document']['template'] : ''),
            $read,
        ));

        $counts = $this->usage->counts();

        if (! $dryRun || ! $publish) {
            return $this->rows($order, $read, $counts, $dryRun, $publish);
        }

        $connection = Block::query()->getConnection();
        $connection->beginTransaction();

        try {
            $rows = $this->rows($order, $read, $counts, false, true, rehearsal: true);
        } finally {
            $connection->rollBack();
            // What the rehearsal wrote may have been read into the caches, which outlive the
            // transaction.
            $this->types->forget();
            $this->thumbnails->forget();
        }

        // Numbers of versions that were never kept.
        return array_map(static fn (array $row): array => ['version' => null, 'published' => null] + $row, $rows);
    }

    /**
     * @param  list<string>  $order
     * @param  array<string, array{name: string, document: array<string, mixed>}>  $read
     * @param  array<string, int>  $counts
     * @return list<array{slug: string, title: string|null, kind: string, name: string, status: string, writes: bool, version: int|null, published: int|null, error: string|null}>
     */
    private function rows(array $order, array $read, array $counts, bool $dryRun, bool $publish, bool $rehearsal = false): array
    {
        $rows = [];

        foreach ($order as $slug) {
            $rows[] = $this->one($slug, $read[$slug]['name'], $read[$slug]['document'], $counts, $dryRun, $publish, $rehearsal);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, int>  $counts
     * @return array{slug: string, title: string|null, kind: string, name: string, status: string, writes: bool, version: int|null, published: int|null, error: string|null}
     */
    private function one(string $slug, string $name, array $document, array $counts, bool $dryRun, bool $publish, bool $rehearsal): array
    {
        $row = [
            'slug' => $slug,
            'title' => is_string($document['title'] ?? null) ? $document['title'] : null,
            'kind' => is_string($document['kind'] ?? null) ? $document['kind'] : Block::KIND_BLOCK,
            'name' => $name,
            'status' => self::FAILED,
            'writes' => false,
            'version' => null,
            'published' => null,
            'error' => null,
        ];

        $block = Block::query()->where('slug', $slug)->with(['draftVersion', 'publishedVersion'])->first();

        $check = $this->validator->make(
            $document,
            BlockInput::rowRules($block === null, $block?->id) + BlockInput::contentRules(),
            BlockInput::messages(),
        );

        if ($check->fails()) {
            return ['error' => implode(' ', $check->errors()->all())] + $row;
        }

        $values = BlockInput::values($document);
        $content = BlockInput::content($document);

        $refusal = $block === null ? null : BlockInput::kindRefusal($block, $values['kind'] ?? null, $counts);

        if ($refusal !== null) {
            return ['error' => $refusal] + $row;
        }

        $creating = $block === null;
        $block ??= new Block;
        $block->fill($values);
        $rowChanged = $creating || $block->isDirty();
        $writes = $creating || ($content !== [] && $block->contentDiffers($content));

        $row['status'] = match (true) {
            $creating => self::CREATED,
            $rowChanged || $writes => self::UPDATED,
            default => self::UNCHANGED,
        };
        $row['writes'] = $writes;

        if ($dryRun) {
            return $row;
        }

        if ($rowChanged) {
            $block->save();
        }

        if ($writes) {
            $row['version'] = $block->saveVersion($content, BlockVersion::SOURCE_IMPORT, null, "Imported from {$name}")->number;
        }

        if ($publish && $block->draftVersion !== null) {
            $refused = $rehearsal ? 'would not be published' : 'not published';

            try {
                $row['published'] = $this->publisher->publish($block)->number;
            } catch (PublishFailed $failure) {
                $line = $failure->failure->templateLine !== null ? " (template line {$failure->failure->templateLine})" : '';
                $row['error'] = "{$refused} — {$failure->describe()}{$line}";
            } catch (DropsTranslations $failure) {
                $row['error'] = "{$refused} — {$failure->getMessage()}";
            } catch (BlocksException $failure) {
                $row['error'] = "{$refused} — {$failure->getMessage()}";
            }
        }

        return $row;
    }
}
