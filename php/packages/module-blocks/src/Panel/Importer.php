<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Rendering\Calls;

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
        $rows = [];

        foreach ($order as $slug) {
            $rows[] = $this->one($slug, $read[$slug]['name'], $read[$slug]['document'], $counts, $dryRun, $publish);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, int>  $counts
     * @return array{slug: string, title: string|null, kind: string, name: string, status: string, writes: bool, version: int|null, published: int|null, error: string|null}
     */
    private function one(string $slug, string $name, array $document, array $counts, bool $dryRun, bool $publish): array
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
            try {
                $row['published'] = $this->publisher->publish($block)->number;
            } catch (PublishFailed $refused) {
                $line = $refused->failure->templateLine !== null ? " (template line {$refused->failure->templateLine})" : '';
                $row['error'] = "not published — {$refused->describe()}{$line}";
            } catch (DropsTranslations $refused) {
                $row['error'] = "not published — {$refused->getMessage()}";
            } catch (BlocksException $refused) {
                $row['error'] = "not published — {$refused->getMessage()}";
            }
        }

        return $row;
    }
}
