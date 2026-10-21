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
use WebxUi\Blocks\Themes\LayerSource;
use WebxUi\Blocks\Themes\TypeHash;

/**
 * Block types back from their documents (§17) — the one door the command and the panel share.
 *
 * Each document is checked by the rules the panel checks a save with, so a document that
 * imports is a type the panel would have accepted. A type the site does not have is created. A
 * type it has is the site's (BLOCK_SCHEMAS §1): the same as the document, it is `unchanged`;
 * different, it `differs` and is left as it is, with what differs ({@see TypeDiff}) — whoever
 * changed it, a client in the panel or an older file. Only a slug named in `$replace` is written
 * over, as a new version, so the one before stays in the history. There is no replacing every
 * type at once ({@see ReplaceRefused}).
 *
 * Publishing, when asked, runs the publish checks on what was created or replaced; a type that
 * fails stays a draft and its row says why.
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

    public const DIFFERS = 'differs';

    public const REPLACED = 'replaced';

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
     * `layer` marks a document read from a theme layer ({@see LayerSource}): what it creates or
     * replaces is told where it came from. `refusal` is a reason the source already found not to take it.
     *
     * @param  list<array{name: string, document: array<string, mixed>, layer?: string, refusal?: string}>  $documents  As {@see Exchange::read()} gives them.
     * @param  list<string>  $replace  The slugs to write over when they differ; each named, none implied.
     * @return list<array{slug: string, title: string|null, kind: string, name: string, status: string, writes: bool, version: int|null, published: int|null, from: int|null, draft: bool, diff: array<string, mixed>|null, error: string|null}>
     *
     * @throws CallCycle A circle among the documents: refused before anything is written.
     * @throws ReplaceRefused Every type asked to be replaced, or a slug the documents do not carry: refused the same way.
     */
    public function import(array $documents, bool $dryRun = false, bool $publish = false, array $replace = []): array
    {
        /** @var array<string, array{name: string, document: array<string, mixed>, layer?: string, refusal?: string}> $read */
        $read = [];

        // The same slug twice in one file: the later one wins, as a second file would.
        foreach ($documents as $one) {
            $read[(string) $one['document']['slug']] = $one;
        }

        ReplaceRefused::check($replace, array_map('strval', array_keys($read)));

        $order = Graph::order(array_map(
            static fn (array $one): array => Calls::of(is_string($one['document']['template'] ?? null) ? $one['document']['template'] : ''),
            $read,
        ));

        $counts = $this->usage->counts();

        if (! $dryRun || ! $publish) {
            return $this->rows($order, $read, $counts, $replace, $dryRun, $publish);
        }

        $connection = Block::query()->getConnection();
        $connection->beginTransaction();

        try {
            $rows = $this->rows($order, $read, $counts, $replace, false, true, rehearsal: true);
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
     * @param  array<string, array{name: string, document: array<string, mixed>, layer?: string, refusal?: string}>  $read
     * @param  array<string, int>  $counts
     * @param  list<string>  $replace
     * @return list<array{slug: string, title: string|null, kind: string, name: string, status: string, writes: bool, version: int|null, published: int|null, from: int|null, draft: bool, diff: array<string, mixed>|null, error: string|null}>
     */
    private function rows(array $order, array $read, array $counts, array $replace, bool $dryRun, bool $publish, bool $rehearsal = false): array
    {
        $rows = [];

        foreach ($order as $slug) {
            $rows[] = $this->one($read[$slug], $counts, in_array($slug, $replace, true), $dryRun, $publish, $rehearsal);
        }

        return $rows;
    }

    /**
     * @param  array{name: string, document: array<string, mixed>, layer?: string, refusal?: string}  $one
     * @param  array<string, int>  $counts
     * @return array{slug: string, title: string|null, kind: string, name: string, status: string, writes: bool, version: int|null, published: int|null, from: int|null, draft: bool, diff: array<string, mixed>|null, error: string|null}
     */
    private function one(array $one, array $counts, bool $replacing, bool $dryRun, bool $publish, bool $rehearsal): array
    {
        $document = $one['document'];
        $name = $one['name'];

        $row = [
            'slug' => (string) $document['slug'],
            'title' => is_string($document['title'] ?? null) ? $document['title'] : null,
            'kind' => is_string($document['kind'] ?? null) ? $document['kind'] : Block::KIND_BLOCK,
            'name' => $name,
            'status' => self::FAILED,
            'writes' => false,
            'version' => null,
            'published' => null,
            'from' => null,
            'draft' => false,
            'diff' => null,
            'error' => null,
        ];

        if (isset($one['refusal'])) {
            return ['error' => $one['refusal']] + $row;
        }

        $block = Block::query()->where('slug', $row['slug'])->with(['draftVersion', 'publishedVersion'])->first();

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

        if ($block !== null) {
            $row['from'] = $block->currentVersion()?->number;
            $row['draft'] = $block->draft_version_id !== null;
            $diff = TypeDiff::of($document, $block);

            if ($diff === []) {
                return ['status' => self::UNCHANGED] + $row;
            }

            $row['diff'] = $diff;

            if (! $replacing) {
                return ['status' => self::DIFFERS] + $row;
            }

            $refusal = BlockInput::kindRefusal($block, $values['kind'] ?? null, $counts);

            if ($refusal !== null) {
                return ['error' => $refusal] + $row;
            }
        }

        $creating = $block === null;
        $block ??= new Block;
        $block->fill($values);

        if (isset($one['layer'])) {
            // Where the type came from, for the debug panel; nothing decides on it any more.
            $block->forceFill(['theme_source' => $one['layer'], 'theme_hash' => TypeHash::ofDocument($document)]);
        }

        $writes = $creating || ($content !== [] && $block->contentDiffers($content));

        $row['status'] = $creating ? self::CREATED : self::REPLACED;
        $row['writes'] = $writes;

        if ($dryRun) {
            return $row;
        }

        if ($block->isDirty()) {
            $block->save();
        }

        if ($writes) {
            $comment = $creating ? "Imported from {$name}" : "Replaced from {$name}";
            $row['version'] = $block->saveVersion($content, BlockVersion::SOURCE_IMPORT, null, $comment)->number;
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
