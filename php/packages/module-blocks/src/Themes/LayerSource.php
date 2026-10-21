<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Themes;

use WebxUi\Themes\Blocks\LayerBlock;
use WebxUi\Themes\Blocks\LayerBlocks;
use WebxUi\Themes\Checks\CssCheck;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\Vocabulary;

/**
 * The block types of the theme layers as documents for `webx:blocks:import --theme`
 * (BLOCK_SCHEMAS §4) — the set the chain resolves to, an upper layer's type replacing the lower
 * one's whole.
 *
 * What only a layer can get wrong is settled here and handed on as a refusal, so the import
 * says it as a failed row and writes nothing for it: a file that is not a block document, styles
 * that break THEMES §8, an override that drops or retypes a field of the type below (rule 16).
 * Everything else is the import's — the panel's rules, the order of calls, publishing.
 *
 * Only used when `webx-ui/themes` is installed: the engine does not know this package, this
 * package knows the engine.
 */
final class LayerSource
{
    /**
     * @return list<array{name: string, document: array<string, mixed>, layer?: string, refusal?: string}>
     */
    public static function read(ThemeChain $chain): array
    {
        $layers = LayerBlocks::of($chain);
        $documents = [];

        // Under the file's name, not a slug: a lower layer's type of the same slug is still the set's.
        foreach ($layers->problems() as $problem) {
            $name = basename($problem['file']);
            $documents[] = ['name' => $name, 'document' => ['slug' => $name], 'refusal' => $problem['message']];
        }

        $incompatible = [];

        foreach ($layers->incompatible() as $finding) {
            $incompatible[basename($finding['file'], '.json')] ??= $finding['message'];
        }

        $vocabulary = self::vocabulary($chain);

        foreach ($layers->set() as $block) {
            $one = ['name' => $block->layer, 'document' => $block->document, 'layer' => $block->layer];
            $refusal = self::styles($block, $vocabulary) ?? $incompatible[$block->slug] ?? null;

            $documents[] = $refusal === null ? $one : $one + ['refusal' => $refusal];
        }

        return $documents;
    }

    private static function styles(LayerBlock $block, Vocabulary $vocabulary): ?string
    {
        $findings = CssCheck::check($block->styles(), $vocabulary);

        if ($findings === []) {
            return null;
        }

        $first = $findings[0];

        return sprintf(
            'its styles break THEMES §8 (%d %s; line %d: %s); webx:theme:check lists them',
            count($findings),
            count($findings) === 1 ? 'finding' : 'findings',
            $first->line,
            $first->message(),
        );
    }

    private static function vocabulary(ThemeChain $chain): Vocabulary
    {
        try {
            return Vocabulary::forChain($chain);
        } catch (ThemeException) {
            // A vocabulary that does not resolve is webx:theme:check's to say; the base one still knows every name of the engine.
            return Vocabulary::base();
        }
    }
}
