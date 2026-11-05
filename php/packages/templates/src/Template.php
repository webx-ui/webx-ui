<?php

declare(strict_types=1);

namespace WebxUi\Templates;

use Closure;
use Mustache\Engine;
use Mustache\Exception\SyntaxException;
use Mustache\Loader\ArrayLoader;
use Mustache\Tokenizer;

/**
 * A template kept in the database — a channel's message, a feed — is Mustache, rendered by
 * `mustache/mustache`: `{{name}}`, a list `{{#items}}…{{/items}}` over the fields of each element,
 * `{{#name}}…{{/name}}` when a value is there and `{{^name}}…{{/name}}` when it is not. It is never
 * Blade and never PHP: Mustache has no way to call code, and lambdas are off.
 *
 * What Mustache has besides is refused: partials `{{>x}}`, a change of delimiters `{{=<% %>=}}`,
 * pragmas, inheritance, dynamic names. `{{{name}}}` and `{{&name}}` are read as `{{name}}` — no
 * value ever goes out unescaped. Escaping is the caller's — a provider's, a format's ({@see Escapes})
 * — and replaces Mustache's own HTML escaping, so nothing is escaped twice.
 *
 * Names are lowercase with one dot at most: `field.phone` is the field `phone` — the values are
 * given flat (`['field.phone' => …]`) and nested before rendering, the way Mustache reads a dot.
 *
 * Every element of a list has `first` and `last`, `'1'` or `''`: Mustache does not know the last
 * element, and a comma between two is what a JSON feed cannot do without.
 * `{{#images}}{{^first}},{{/first}}"{{url}}"{{/images}}`. A field of that name of its own wins.
 *
 * `$escape` takes the value, and `true` after it when the value is a {@see Cdata}: a closure that
 * does not care leaves the second argument out and gets the text like any value.
 *
 * @phpstan-type Values array<string, string|Cdata|null|list<array<string, string|Cdata|null>>>
 * @phpstan-type Usage array{name: string, sections: list<string>, kind: string}
 */
final class Template
{
    public const NAME = '/^[a-z0-9_]+(\.[a-z0-9_]+)?$/';

    /** A variable printed. */
    public const VALUE = 'value';

    /** A name a section opens: `{{#name}}` or `{{^name}}`. */
    public const SECTION = 'section';

    /** What every element of a list has besides its own fields. */
    public const POSITIONS = ['first', 'last'];

    private const REFUSED = [
        Tokenizer::T_PARTIAL => 'template-partial',
        Tokenizer::T_PARENT => 'template-partial',
        Tokenizer::T_DELIM_CHANGE => 'template-delimiters',
        Tokenizer::T_PRAGMA => 'template-pragma',
        Tokenizer::T_BLOCK_VAR => 'template-pragma',
    ];

    private const PREFIX = [
        Tokenizer::T_SECTION => '#',
        Tokenizer::T_INVERTED => '^',
        Tokenizer::T_END_SECTION => '/',
        Tokenizer::T_COMMENT => '!',
    ];

    /**
     * Every name the template uses, in order, with the sections it is inside — innermost last.
     *
     * @return list<Usage>
     *
     * @throws TemplateError
     */
    public static function usages(string $template): array
    {
        $usages = [];
        self::walk(self::tree($template), [], static function (string $name, array $sections, string $kind) use (&$usages): void {
            $usages[] = ['name' => $name, 'sections' => $sections, 'kind' => $kind];
        });

        return $usages;
    }

    /**
     * The template with the values in, each through `$escape`. A name with no value is empty; it
     * is reported as unknown unless `$known` says the event declares it (an empty field is not a
     * mistake in the template).
     *
     * @param  Values  $values
     * @param  Closure(string, bool=): string  $escape
     * @param  (Closure(string, list<string>): bool)|null  $known  a name and the sections it is inside
     * @return array{text: string, unknown: list<string>}
     *
     * @throws TemplateError
     */
    public static function render(string $template, array $values, Closure $escape, ?Closure $known = null): array
    {
        $tree = self::tree($template);
        $context = self::nest($values);
        $unknown = [];

        self::walk($tree, [], static function (string $name, array $sections, string $kind) use ($context, $known, &$unknown): void {
            if (! self::present($context, $name, $sections) && ($known === null || ! $known($name, $sections))) {
                $unknown[$name] = true;
            }
        });

        return ['text' => self::compile($template, $escape)->render($values), 'unknown' => array_keys($unknown)];
    }

    /**
     * The template read and checked once, for what renders it many times: nothing is looked for
     * in the values then — unknown names are the business of the check on saving.
     *
     * @param  Closure(string, bool=): string  $escape
     *
     * @throws TemplateError
     */
    public static function compile(string $template, Closure $escape): Compiled
    {
        $engine = new Engine([
            'escape' => static function (mixed $value) use ($escape): string {
                if ($value instanceof Cdata) {
                    return $escape($value->toText(), true);
                }

                return is_scalar($value) ? $escape((string) $value) : '';
            },
            'partials_loader' => new ArrayLoader([]),
            'lambdas' => false,
            'inheritance' => false,
            'dynamic_names' => false,
            'strict_callables' => true,
        ]);

        return new Compiled($engine->loadTemplate(self::source($template)));
    }

    /**
     * Refuses a variable inside a literal `<![CDATA[ … ]]>` of the template: the escaping does not
     * see where a tag stands, so the value would be escaped for XML inside a block that prints
     * entities as they are. A `_cdata` variable says it instead, without the block around it.
     *
     * Not a part of the check every template passes: only a format that writes XML has a reason
     * to ask, and a message that happens to contain the words stays as it was.
     *
     * @throws TemplateError
     */
    public static function refuseInCdata(string $template): void
    {
        $inside = false;

        foreach (self::tokens($template) as $token) {
            $type = $token[Tokenizer::TYPE];

            if ($type === Tokenizer::T_TEXT) {
                // A section may cross the bounds of a block, so the walk is over the flat tokens.
                $text = (string) $token[Tokenizer::VALUE];
                $opened = strrpos($text, '<![CDATA[');
                $closed = strrpos($text, ']]>');

                if ($opened !== false || $closed !== false) {
                    $inside = $opened !== false && ($closed === false || $opened > $closed);
                }

                continue;
            }

            if ($inside && in_array($type, [Tokenizer::T_ESCAPED, Tokenizer::T_UNESCAPED, Tokenizer::T_UNESCAPED_2], true)) {
                throw new TemplateError('template-cdata', ['name' => (string) $token[Tokenizer::NAME]]);
            }
        }
    }

    /**
     * The tokens of a template, checked: Mustache's syntax, nothing refused, names as they are.
     *
     * @return list<array<string, mixed>>
     *
     * @throws TemplateError
     */
    private static function tokens(string $template): array
    {
        try {
            /** @var list<array<string, mixed>> $tokens */
            $tokens = (new Tokenizer)->scan($template);
        } catch (SyntaxException $exception) {
            throw self::syntax($exception);
        }

        foreach ($tokens as $token) {
            $type = $token[Tokenizer::TYPE];

            if (is_string($type) && array_key_exists($type, self::REFUSED)) {
                throw new TemplateError(self::REFUSED[$type]);
            }

            if ($type === Tokenizer::T_TEXT || $type === Tokenizer::T_COMMENT) {
                continue;
            }

            $name = (string) ($token[Tokenizer::NAME] ?? '');

            if (preg_match(self::NAME, $name) !== 1) {
                throw new TemplateError('template-name', ['name' => $name]);
            }
        }

        return $tokens;
    }

    /**
     * Sections nested: what is inside each, closed in order.
     *
     * @return list<array<string, mixed>>
     *
     * @throws TemplateError
     */
    private static function tree(string $template): array
    {
        $tokens = self::tokens($template);
        $at = 0;

        return self::nodes($tokens, $at, null);
    }

    /**
     * The nodes up to the end of the section `$open`, or of the template.
     *
     * @param  list<array<string, mixed>>  $tokens
     * @return list<array<string, mixed>>
     *
     * @throws TemplateError
     */
    private static function nodes(array $tokens, int &$at, ?string $open): array
    {
        $nodes = [];

        while ($at < count($tokens)) {
            $token = $tokens[$at++];
            $type = $token[Tokenizer::TYPE];

            if ($type === Tokenizer::T_SECTION || $type === Tokenizer::T_INVERTED) {
                $token['nodes'] = self::nodes($tokens, $at, (string) $token[Tokenizer::NAME]);
                $nodes[] = $token;

                continue;
            }

            if ($type === Tokenizer::T_END_SECTION) {
                $name = (string) $token[Tokenizer::NAME];

                if ($open === null) {
                    throw new TemplateError('template-unexpected', ['section' => $name]);
                }

                if ($name !== $open) {
                    throw new TemplateError('template-unclosed', ['section' => $open]);
                }

                return $nodes;
            }

            $nodes[] = $token;
        }

        if ($open !== null) {
            throw new TemplateError('template-unclosed', ['section' => $open]);
        }

        return $nodes;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<string>  $sections
     * @param  Closure(string, list<string>, string): void  $visit
     */
    private static function walk(array $nodes, array $sections, Closure $visit): void
    {
        foreach ($nodes as $node) {
            $type = $node[Tokenizer::TYPE];

            if ($type === Tokenizer::T_TEXT || $type === Tokenizer::T_COMMENT) {
                continue;
            }

            $name = (string) $node[Tokenizer::NAME];

            if ($type === Tokenizer::T_SECTION || $type === Tokenizer::T_INVERTED) {
                $visit($name, $sections, self::SECTION);
                /** @var list<array<string, mixed>> $inside */
                $inside = $node['nodes'];
                // An inverted section is shown when there is nothing to step into.
                self::walk($inside, $type === Tokenizer::T_SECTION ? [...$sections, $name] : $sections, $visit);

                continue;
            }

            $visit($name, $sections, self::VALUE);
        }
    }

    /**
     * The template as Mustache is given it: `{{{name}}}` and `{{&name}}` as `{{name}}`. Built from
     * the tokens rather than replaced in the text, so text that only looks like a tag is kept.
     *
     * @throws TemplateError
     */
    private static function source(string $template): string
    {
        $source = '';

        foreach (self::tokens($template) as $token) {
            $type = $token[Tokenizer::TYPE];

            $source .= $type === Tokenizer::T_TEXT
                ? (string) $token[Tokenizer::VALUE]
                : '{{'.(is_string($type) ? self::PREFIX[$type] ?? '' : '').$token[Tokenizer::NAME].'}}';
        }

        return $source;
    }

    /**
     * Flat values nested the way Mustache reads a dot — `field.phone` is `field` → `phone` — with
     * `first` and `last` on the elements of every list.
     *
     * @internal
     *
     * @param  Values  $values
     * @return array<string, mixed>
     */
    public static function nest(array $values): array
    {
        $context = [];

        foreach ($values as $name => $value) {
            $name = (string) $name;

            if (! str_contains($name, '.')) {
                $context[$name] = $value;

                continue;
            }

            [$head, $tail] = explode('.', $name, 2);

            if (! array_key_exists($head, $context) || is_array($context[$head])) {
                $context[$head] = [...(array) ($context[$head] ?? []), $tail => $value];
            }
        }

        return self::positioned($context);
    }

    /**
     * @template T of array<array-key, mixed>
     *
     * @param  T  $values
     * @return T
     */
    private static function positioned(array $values): array
    {
        $list = array_is_list($values);
        $last = count($values) - 1;

        foreach ($values as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $value = self::positioned($value);

            if ($list && ($value === [] || ! array_is_list($value))) {
                $value += ['first' => $key === 0 ? '1' : '', 'last' => $key === $last ? '1' : ''];
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Whether a name has a value where it is used: in the element of an enclosing list, then
     * outwards to the values — how Mustache looks it up.
     *
     * @param  array<string, mixed>  $context
     * @param  list<string>  $sections
     */
    private static function present(array $context, string $name, array $sections): bool
    {
        $scopes = [$context];

        foreach ($sections as $section) {
            $value = self::find($scopes, $section);

            if (is_array($value) && array_is_list($value)) {
                // The fields of any element: a list of rows with gaps is not a mistake.
                $scopes[] = array_merge(...array_values(array_filter($value, is_array(...))));
            } elseif (is_array($value)) {
                $scopes[] = $value;
            }
        }

        return self::find($scopes, $name) !== null;
    }

    /**
     * @param  list<array<array-key, mixed>>  $scopes
     */
    private static function find(array $scopes, string $name): mixed
    {
        $chunks = explode('.', $name);

        foreach (array_reverse($scopes) as $scope) {
            if (! array_key_exists($chunks[0], $scope)) {
                continue;
            }

            $value = $scope[$chunks[0]];

            foreach (array_slice($chunks, 1) as $chunk) {
                if (! is_array($value) || ! array_key_exists($chunk, $value)) {
                    return null;
                }

                $value = $value[$chunk];
            }

            return $value;
        }

        return null;
    }

    private static function syntax(SyntaxException $exception): TemplateError
    {
        $token = $exception->getToken();
        $name = (string) ($token[Tokenizer::NAME] ?? '');

        return match ($token[Tokenizer::TYPE] ?? null) {
            Tokenizer::T_DELIM_CHANGE => new TemplateError('template-delimiters'),
            Tokenizer::T_SECTION, Tokenizer::T_INVERTED => new TemplateError('template-unclosed', ['section' => $name]),
            default => new TemplateError('template-tag', ['tag' => $name]),
        };
    }
}
