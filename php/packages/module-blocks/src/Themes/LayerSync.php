<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Themes;

use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebxUi\Blocks\Demo\BlocksDemo;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Panel\CallCycle;
use WebxUi\Blocks\Panel\Graph;
use WebxUi\Blocks\Rendering\Calls;
use WebxUi\Themes\Blocks\LayerBlock;
use WebxUi\Themes\Blocks\LayerBlocks;
use WebxUi\Themes\Checks\CssCheck;
use WebxUi\Themes\Contracts\SyncStep;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\Sync\SyncLine;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\Vocabulary;

/**
 * The block types of the theme layers, put on the site by `webx:theme:sync` (THEMES §9.2).
 *
 * The engine reads the layers and settles which type wins a slug; this decides what happens to
 * the site's table, in the six steps of the spec: check, hash, create what is missing, update
 * what the site has not touched, keep what it has (unless `--force`), and refuse a slug that is
 * the site's own — taking it over would overwrite somebody's work on the next update. A type a
 * layer stopped carrying is not deleted: pages may stand on it.
 *
 * The one exception is asked for by name: `--adopt=hero` hands the theme a type the blocks demo
 * seeded and nobody has changed since — a site that got the demo before its theme carried the
 * same slug. Anything else under the slug stays refused, adopt or not.
 *
 * Registered as a {@see SyncStep} only when `webx-ui/themes` is installed: the engine does not
 * know this package, this package knows the engine.
 */
final class LayerSync implements SyncStep
{
    public function __construct(
        private readonly ValidatorFactory $validator,
        private readonly ConnectionInterface $db,
    ) {}

    public function title(): string
    {
        return 'Block types';
    }

    public function sync(ThemeChain $chain, bool $force, bool $dryRun, array $adopt = []): array
    {
        // `webx:theme:sync` is the site's post-update-cmd, and Composer runs where the database
        // may be out of reach — a Docker build, a host whose site talks to the database of its
        // container. The files are published by then; this step says what it could not do
        // instead of failing `composer update` with an SQL error.
        try {
            $this->db->select('select 1');
        } catch (Throwable) {
            return [new SyncLine('blocks', 'the database is not reachable from here — the layers\' block types were not put on the site; run php artisan webx:theme:sync where it is')];
        }

        $layers = LayerBlocks::of($chain);
        $lines = [];

        foreach ($layers->problems() as $problem) {
            $lines[] = SyncLine::refused(basename($problem['file']), $problem['message']);
        }

        $set = $layers->set();

        if ($set === [] && $lines === []) {
            return $this->leftTheLayers($set);
        }

        // Read before anything is written, so that a site on the release before this column
        // hears what to do instead of an SQL error in the middle of `composer update`.
        if (! Schema::hasColumn('blocks', 'theme_hash')) {
            return [new SyncLine('blocks', 'the table has no theme_source yet — run php artisan migrate, then webx:theme:sync')];
        }

        $vocabulary = $this->vocabulary($chain);
        $incompatible = [];

        foreach ($layers->incompatible() as $finding) {
            $incompatible[basename($finding['file'], '.json')][] = $finding['message'];
        }

        foreach ($this->order($set) as $block) {
            $lines[] = $this->one($block, $vocabulary, $incompatible[$block->slug] ?? [], $force, $dryRun, in_array($block->slug, $adopt, true));
        }

        return [...$lines, ...$this->leftTheLayers($set)];
    }

    /**
     * @param  list<string>  $incompatible
     */
    private function one(LayerBlock $block, Vocabulary $vocabulary, array $incompatible, bool $force, bool $dryRun, bool $adopt): SyncLine
    {
        // 1. The checks: the rules of the panel's save, §8, and a schema the type below can live in.
        $existing = Block::query()->with(['draftVersion', 'publishedVersion'])->where('slug', $block->slug)->first();
        $refusal = $this->refusal($block, $vocabulary, $incompatible, $existing);

        if ($refusal !== null) {
            return SyncLine::refused($block->slug, $refusal);
        }

        // 2. The hash of what the layer carries.
        $hash = TypeHash::ofDocument($block->document);

        // 3. Not on the site: put it there.
        if ($existing === null) {
            if ($dryRun) {
                return new SyncLine($block->slug, "would be created from {$block->layer}");
            }

            return $this->write(new Block, $block, $hash, 'created');
        }

        // 6. The site's own type, or one a module offered, under the same slug. Never taken over
        // unasked; asked, only the demo's copy nobody touched — the one type of the site's that
        // holds nobody's work (THEMES §23, TH2.3).
        if ($existing->theme_source === null) {
            $demo = BlocksDemo::untouched($existing);

            if ($adopt && $demo) {
                if ($dryRun) {
                    return new SyncLine($block->slug, "would be adopted from {$block->layer} (the blocks demo's, untouched)");
                }

                return $this->write($existing, $block, $hash, 'adopted');
            }

            if ($adopt) {
                return SyncLine::refused(
                    $block->slug,
                    'not adopted — --adopt takes only the blocks demo\'s copy nobody has changed, and this one is the site\'s own work. '
                    .'Rename or delete it in the panel, then sync again',
                );
            }

            return SyncLine::refused(
                $block->slug,
                "this site has a type of its own by this slug — {$block->layer} does not take it over. "
                .($demo
                    ? "It is the blocks demo's {$block->slug}, unchanged: webx:theme:sync --adopt={$block->slug} hands it to the theme"
                    : 'Rename or delete it in the panel, then sync again; or keep it and leave the layer\'s one out'),
            );
        }

        $published = TypeHash::ofPublished($existing);

        if ($published === $hash && $existing->draft_version_id === null) {
            // The same type, now carried by another layer — the local theme took it over word for word.
            if (! $dryRun && ($existing->theme_source !== $block->layer || $existing->theme_hash !== $hash)) {
                $existing->forceFill(['theme_source' => $block->layer, 'theme_hash' => $hash])->save();
            }

            return new SyncLine($block->slug, 'unchanged');
        }

        // 4 and 5. Untouched here since the last sync — or touched, and kept unless forced.
        $touched = $published !== $existing->theme_hash || $existing->draft_version_id !== null;

        if ($touched && ! $force) {
            return new SyncLine($block->slug, 'changed on this site since the theme last set it, kept (--force overwrites)');
        }

        if ($dryRun) {
            return new SyncLine($block->slug, ($touched ? 'would be overwritten' : 'would be updated')." from {$block->layer}");
        }

        return $this->write($existing, $block, $hash, $touched ? 'overwritten' : 'updated');
    }

    /**
     * @param  list<string>  $incompatible
     */
    private function refusal(LayerBlock $block, Vocabulary $vocabulary, array $incompatible, ?Block $existing): ?string
    {
        $check = $this->validator->make(
            $block->document,
            BlockInput::rowRules(true, $existing?->theme_source !== null ? $existing->id : null) + BlockInput::contentRules(),
            BlockInput::messages(),
        );

        // A slug taken by the site's own type is step 6, said in its own words below.
        $errors = array_values(array_filter(
            $check->errors()->messages(),
            static fn (string $key): bool => ! ($key === 'slug' && $existing !== null && $existing->theme_source === null),
            ARRAY_FILTER_USE_KEY,
        ));

        if ($errors !== []) {
            return 'refused: '.implode(' ', array_merge(...$errors));
        }

        $findings = CssCheck::check($block->styles(), $vocabulary);

        if ($findings !== []) {
            $first = $findings[0];

            return sprintf(
                'refused — its styles break THEMES §8 (%d %s; line %d: %s); webx:theme:check lists them',
                count($findings),
                count($findings) === 1 ? 'finding' : 'findings',
                $first->line,
                $first->message(),
            );
        }

        if ($incompatible !== []) {
            return 'refused — '.$incompatible[0];
        }

        return null;
    }

    /**
     * A new version from the layer's file, published, and the row told where it came from. In a
     * transaction: a template that will not publish leaves nothing behind, not a draft nobody
     * asked for.
     */
    private function write(Block $row, LayerBlock $block, string $hash, string $done): SyncLine
    {
        try {
            $this->db->transaction(function () use ($row, $block, $hash): void {
                $row->fill(BlockInput::values($block->document));
                $row->forceFill(['theme_source' => $block->layer, 'theme_hash' => $hash]);
                $row->save();

                $row->saveVersion(BlockInput::content($block->document), BlockVersion::SOURCE_THEME, null, "From {$block->layer}");
                $row->publish();
            });
        } catch (BlocksException $failed) {
            return SyncLine::refused($block->slug, 'refused — it does not publish: '.$failed->getMessage());
        } catch (Throwable $failed) {
            return SyncLine::refused($block->slug, 'refused — '.$failed->getMessage());
        }

        return new SyncLine($block->slug, "{$done} from {$block->layer}");
    }

    /**
     * What is called first: publishing checks a type against the types its template calls.
     *
     * @param  array<string, LayerBlock>  $set
     * @return list<LayerBlock>
     */
    private function order(array $set): array
    {
        try {
            $slugs = Graph::order(array_map(
                static fn (LayerBlock $block): array => Calls::of(is_string($block->document['template'] ?? null) ? $block->document['template'] : ''),
                $set,
            ));
        } catch (CallCycle) {
            // Publishing refuses a circle with its own words; the order no longer matters.
            $slugs = array_keys($set);
        }

        return array_map(static fn (string $slug): LayerBlock => $set[$slug], $slugs);
    }

    /**
     * Types a layer put here that no layer carries any more: said, never deleted.
     *
     * @param  array<string, LayerBlock>  $set
     * @return list<SyncLine>
     */
    private function leftTheLayers(array $set): array
    {
        if (! Schema::hasColumn('blocks', 'theme_source')) {
            return [];
        }

        return Block::query()
            ->whereNotNull('theme_source')
            ->whereNotIn('slug', array_keys($set) === [] ? [''] : array_map('strval', array_keys($set)))
            ->orderBy('slug')
            ->get()
            ->map(static fn (Block $left): SyncLine => new SyncLine($left->slug, "{$left->theme_source} no longer carries it — kept, pages may stand on it"))
            ->values()
            ->all();
    }

    private function vocabulary(ThemeChain $chain): Vocabulary
    {
        try {
            return Vocabulary::forChain($chain);
        } catch (ThemeException) {
            // A vocabulary that does not resolve is webx:theme:check's to say; the base one still knows every name of the engine.
            return Vocabulary::base();
        }
    }
}
