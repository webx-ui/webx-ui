<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\ScreenValues;

/**
 * `wx-rich-text`: a document, kept as HTML.
 *
 * HTML rather than a node tree because that is what a column holds anyway and what a template
 * prints — a site that wants the text has nothing to render a tree with. The limit is generous:
 * an article is not a title, and a node that means a short lead says so with `props.maxlength`.
 *
 * Localized needs nothing here. The language map is picked apart one layer up
 * ({@see ScreenValues}), which validates and stores each language against these same rules, so
 * a translated article is this type run once per language.
 */
final class RichTextType implements FieldType
{
    /**
     * Long enough for a page of an article and short of what a `LONGTEXT` column takes. A
     * number so that a runaway paste is a validation error rather than a truncated row.
     */
    public const MAX = 262144;

    /**
     * `null` on a site with no file manager: there is then nothing to ask where a key lives,
     * and a document is read exactly as it was written.
     */
    public function __construct(private readonly ?AssetUrls $assets = null) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $max = $node['props']['maxlength'] ?? self::MAX;

        return ['nullable', 'string', 'max:'.(int) $max];
    }

    /**
     * What is kept: the document, with everything the allowlist does not name taken out.
     *
     * Sanitised on the way in rather than on the way out, because the way out is a template
     * printing it raw, and there may be several of them. The editor is not the only way in
     * either — the same field takes a POST, and an agent writing through a tool never opens it.
     *
     * The `src` of a picture from the library is kept as it came, and it is a cache and not the
     * record: {@see Html::KEY} beside it is the record, and {@see self::resolve()} works the
     * address out again. Kept rather than dropped because the panel reads values raw — it edits
     * what is stored, not what a site would print — so a document with no addresses in it would
     * open in the editor with a hole where every picture was.
     *
     * An emptied editor leaves `<p></p>` behind; nothing is what that means, and a column
     * holding it would make "did anybody write anything" a parse rather than a check.
     *
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = Html::clean($value);

        return Html::isEmpty($clean) ? null : $clean;
    }

    /**
     * What the site reads: the document with every library picture pointed at where it lives
     * now rather than where it lived when somebody wrote the paragraph around it.
     *
     * This is the same rule `wx-media` has always followed — the key is the record, the address
     * is worked out — one layer further in, because a rich text field holds its pictures inside
     * a value instead of being one.
     *
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        if (! is_string($stored) || $this->assets === null) {
            return $stored;
        }

        $assets = $this->assets;

        return Html::rewrite($stored, static fn (array $paths): array => $assets->urls($paths));
    }
}
