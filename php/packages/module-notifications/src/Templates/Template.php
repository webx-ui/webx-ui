<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Templates;

use Closure;
use Mustache\Engine;
use Mustache\Exception\SyntaxException;
use Mustache\Loader\ArrayLoader;
use Mustache\Tokenizer;

/**
 * A channel's template is Mustache (§7, decision 16), rendered by `mustache/mustache`: `{{name}}`,
 * a list `{{#items}}…{{/items}}` over the fields of each element, `{{#name}}…{{/name}}` when a
 * value is there and `{{^name}}…{{/name}}` when it is not. A template is stored in the database,
 * so it is never Blade and never PHP: Mustache has no way to call code, and lambdas are off.
 *
 * What Mustache has besides is refused: partials `{{>x}}`, a change of delimiters `{{=<% %>=}}`,
 * pragmas, inheritance, dynamic names. `{{{name}}}` and `{{&name}}` are read as `{{name}}` — no
 * value ever reaches a message unescaped. Escaping is the provider's: it replaces Mustache's own
 * HTML escaping, so nothing is escaped twice.
 *
 * Names are lowercase with one dot at most: `field.phone` is the field `phone` — the values are
 * given flat (`['field.phone' => …]`) and nested before rendering, the way Mustache reads a dot.
 *
 * @phpstan-type Values array<string, string|list<array<string, string>>>
 * @phpstan-type Usage array{name: string, sections: list<string>, kind: string}
 */
final class Template
{
    public const NAME = '/^[a-z0-9_]+(\.[a-z0-9_]+)?$/';

    /** A variable printed. */
    public const VALUE = 'value';

    /** A name a section opens: `{{#name}}` or `{{^name}}`. */
    public const SECTION = 'section';

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
     * @param  Closure(string): string  $escape
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

        $engine = new Engine([
            'escape' => static fn (mixed $value): string => is_scalar($value) ? $escape((string) $value) : '',
            'partials_loader' => new ArrayLoader([]),
            'lambdas' => false,
            'inheritance' => false,
            'dynamic_names' => false,
            'strict_callables' => true,
        ]);

        return ['text' => $engine->render(self::source($template), $context), 'unknown' => array_keys($unknown)];
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
     * Flat values nested the way Mustache reads a dot: `field.phone` is `field` → `phone`.
     *
     * @param  Values  $values
     * @return array<string, mixed>
     */
    private static function nest(array $values): array
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

        return $context;
    }

    /**
     * Whether a name has a value where it is used: in the element of an enclosing list, then
     * outwards to the event's values — how Mustache looks it up.
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
