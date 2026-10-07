<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Container\Container;
use ParseError;
use Throwable;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Calls;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Blocks\Schema;

/**
 * What is said on saving and never refused (§15): selectors outside the block's prefix,
 * bare element selectors, a media query where a container query belongs, no `data-wx-block`
 * on the root. Each is a habit that bites later — on another block, on another page — so
 * each is worth a line under the editor, and none is worth a locked save.
 *
 * The same checks run live in the editor, in `lint.ts`; this is the copy the server sends back
 * with the saved version, so that an agent writing through MCP hears them too — the two that
 * refuse publication above all: a variable the schema does not declare, and a template that does
 * not compile. Two more are about the schema: a field of a type this site does not know (the form
 * draws a warning in its place and nothing checks its value) and an id that cannot be a key.
 */
final class Lints
{
    /** Variables Blade or the renderer hand a template on their own — `GIVEN` in `lint.ts`. */
    private const GIVEN = ['block', 'entity', 'loop', 'slot', '__env', 'errors', 'app', 'region', 'attributes', 'this'];

    /** What a field id may be: a key of the values, and a variable when it is a valid PHP name. */
    public const FIELD_ID = '/^[A-Za-z_][A-Za-z0-9_-]*$/';

    /**
     * @param  list<array<string, mixed>>|null  $schema  Null skips the checks that need one.
     * @return list<array{file: string, code: string, line: int|null, message: string}>
     */
    public static function check(string $slug, string $template, string $styles, ?array $schema = null): array
    {
        $lints = [];

        if (! preg_match('/data-wx-block\s*=/', $template)) {
            $lints[] = self::lint('template', 'no-marker', null);
        }

        $syntax = self::syntax($slug, $template);

        if ($syntax !== null) {
            $lints[] = $syntax;
        }

        if ($schema !== null) {
            $missing = self::undeclared($template, $schema);

            if ($missing !== []) {
                $lints[] = self::lint('template', 'variables-missing', null, [
                    'variables' => implode(', ', array_map(static fn (string $name): string => '$'.$name, $missing)),
                ]);
            }

            $lints = [...$lints, ...self::schema($schema)];
        }

        $lints = [...$lints, ...self::calls($template)];

        $stray = [];
        $bare = [];
        $prefix = '.b-'.$slug;

        foreach (self::selectors($styles) as [$selector, $line]) {
            if (str_starts_with($selector, $prefix) || str_starts_with($selector, '&')) {
                continue;
            }

            if (preg_match('/^[a-z][a-z0-9-]*/i', $selector)) {
                $bare[] = [$selector, $line];
            } else {
                $stray[] = [$selector, $line];
            }
        }

        if ($stray !== []) {
            $lints[] = self::lint('styles', 'stray-selectors', $stray[0][1], ['slug' => $slug, 'selectors' => self::few($stray)]);
        }

        if ($bare !== []) {
            $lints[] = self::lint('styles', 'bare-selectors', $bare[0][1], ['selectors' => self::few($bare)]);
        }

        if (preg_match('/@media\b/', self::withoutComments($styles), $match, PREG_OFFSET_CAPTURE)) {
            $lints[] = self::lint('styles', 'media-query', self::lineAt($styles, (int) $match[0][1]));
        }

        return $lints;
    }

    /**
     * Variables the template reads that nothing declares: not the schema, not Blade's own, not a
     * `@foreach (… as $item)` or an assignment in the template itself — `undeclared()` in
     * `lint.ts`, rule for rule.
     *
     * @param  list<array<string, mixed>>  $schema
     * @return list<string>
     */
    public static function undeclared(string $template, array $schema): array
    {
        $declared = array_flip([...self::ids($schema), ...self::GIVEN]);

        preg_match_all('/\bas\s+\$([a-zA-Z_]\w*)(?:\s*=>\s*\$([a-zA-Z_]\w*))?/', $template, $loops, PREG_SET_ORDER);

        foreach ($loops as $match) {
            $declared[$match[1]] = true;

            if (($match[2] ?? '') !== '') {
                $declared[$match[2]] = true;
            }
        }

        preg_match_all('/\$([a-zA-Z_]\w*)\s*(?:=[^=>]|\+\+|--|\.=|\+=|-=|\?\?=)/', $template, $assigned);

        foreach ($assigned[1] as $name) {
            $declared[$name] = true;
        }

        // A closure's parameters and what it `use`s are its own: `fn ($card) =>`, `function ($x)`.
        preg_match_all('/(?:fn|function)\s*\(([^)]*)\)/', $template, $closures);

        foreach ($closures[1] as $parameters) {
            preg_match_all('/\$([a-zA-Z_]\w*)/', $parameters, $names);

            foreach ($names[1] as $name) {
                $declared[$name] = true;
            }
        }

        $used = [];

        preg_match_all('/\$([a-zA-Z_]\w*)/', $template, $all);

        foreach ($all[1] as $name) {
            if (! isset($declared[$name]) && ! in_array($name, $used, true)) {
                $used[] = $name;
            }
        }

        return $used;
    }

    /**
     * A template that does not compile, said before anybody renders it: compiled the way the
     * renderer compiles it and parsed, not run.
     *
     * @return array{file: string, code: string, line: int|null, message: string}|null
     */
    private static function syntax(string $slug, string $template): ?array
    {
        if (trim($template) === '') {
            return null;
        }

        try {
            $compiler = Container::getInstance()->make(TemplateCompiler::class);
            $compiled = Container::getInstance()->make('blade.compiler')->compileString($template);
        } catch (Throwable $failure) {
            return self::lint('template', 'syntax', null, ['reason' => $failure->getMessage()]);
        }

        try {
            // Parsed, not run: a syntax error is a ParseError thrown here, and nothing executes.
            $tokens = token_get_all((string) $compiled, TOKEN_PARSE);
        } catch (ParseError $failure) {
            $line = $compiler->templateLine($template, $failure->getLine());

            return self::lint('template', 'syntax', $line, ['reason' => $failure->getMessage()]);
        }

        return null;
    }

    /**
     * What is wrong with the schema itself: fields of a type nobody registered, and ids that
     * cannot be a key.
     *
     * @param  list<array<string, mixed>>  $schema
     * @return list<array{file: string, code: string, line: int|null, message: string}>
     */
    private static function schema(array $schema): array
    {
        $types = Container::getInstance()->make(FieldTypes::class);
        $unknown = [];
        $badIds = [];

        $walk = static function (array $nodes) use (&$walk, $types, &$unknown, &$badIds): void {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $type = (string) ($node['type'] ?? '');
                $id = $node['id'] ?? null;

                if ($type !== '' && ! $types->has($type) && ! Schema::isLayout($type) && $type !== 'wx-blocks') {
                    $unknown[] = (is_string($id) ? $id.': ' : '').$type;
                }

                if (is_string($id) && preg_match(self::FIELD_ID, $id) !== 1) {
                    $badIds[] = '"'.$id.'"';
                }

                $walk(Tree::children($node));
            }
        };

        $walk($schema);

        $lints = [];

        if ($unknown !== []) {
            $lints[] = self::lint('schema', 'unknown-field-type', null, ['fields' => implode(', ', array_unique($unknown))]);
        }

        if ($badIds !== []) {
            $lints[] = self::lint('schema', 'field-id', null, ['ids' => implode(', ', array_unique($badIds))]);
        }

        return $lints;
    }

    /**
     * Every id the schema declares, through its layout and into a repeater — the names a template
     * may read, its loops included.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<string>
     */
    private static function ids(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (is_string($node['id'] ?? null) && $node['id'] !== '') {
                $ids[] = $node['id'];
            }

            $ids = [...$ids, ...self::ids(Tree::children($node))];
        }

        return $ids;
    }

    /**
     * The tags the template calls other types with (§3.8 of the components spec): a type that
     * does not exist — a typo prints nothing on the site, only a line in the log — and a type
     * that is not a literal, which the graph cannot follow, so publishing what it calls will not
     * check this one. Calling a plain block is not worth a word: that is allowed.
     *
     * @return list<array{file: string, code: string, line: int|null, message: string}>
     */
    private static function calls(string $template): array
    {
        $lints = [];
        $dynamic = Calls::dynamic($template);

        if ($dynamic !== []) {
            $lints[] = self::lint('template', 'dynamic-call', $dynamic[0], [], 'calls');
        }

        $calls = Calls::withLines($template);

        if ($calls === []) {
            return $lints;
        }

        try {
            $known = Block::query()->pluck('slug')->all();
        } catch (Throwable) {
            // No tables to ask: nothing to say about names.
            return $lints;
        }

        $reported = [];

        foreach ($calls as [$slug, $line]) {
            if (! in_array($slug, $known, true) && ! isset($reported[$slug])) {
                $reported[$slug] = true;
                $lints[] = self::lint('template', 'unknown-call', $line, ['type' => $slug], 'calls');
            }
        }

        return $lints;
    }

    /**
     * Every selector of every rule with the line it starts on. Preludes of at-rules are
     * skipped; so are the steps of a `@keyframes` block, which look like element selectors and
     * are not.
     *
     * @return list<array{string, int}>
     */
    private static function selectors(string $styles): array
    {
        $source = self::withoutComments($styles);
        $found = [];

        preg_match_all('/([^{};]+)\{/', $source, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as $match) {
            $prelude = trim($match[1][0]);
            $offset = (int) $match[1][1];

            if ($prelude === '' || str_starts_with($prelude, '@')) {
                continue;
            }

            foreach (explode(',', $prelude) as $selector) {
                $selector = trim($selector);

                if ($selector === '' || $selector === 'from' || $selector === 'to' || str_ends_with($selector, '%')) {
                    continue;
                }

                $found[] = [$selector, self::lineAt($source, $offset + strpos($match[1][0], $selector[0]))];
            }
        }

        return $found;
    }

    /** Comments replaced by spaces of the same length, so offsets and lines stay true. */
    private static function withoutComments(string $styles): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            static fn (array $match): string => preg_replace('/[^\n]/', ' ', $match[0]) ?? '',
            $styles,
        );
    }

    private static function lineAt(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, max(0, $offset)) + 1;
    }

    /**
     * @param  list<array{string, int}>  $selectors
     */
    private static function few(array $selectors): string
    {
        $names = array_values(array_unique(array_map(static fn (array $one): string => $one[0], $selectors)));
        $shown = array_slice($names, 0, 3);

        return implode(', ', $shown).(count($names) > 3 ? ', …' : '');
    }

    /**
     * The words of the call lints live in `calls`, beside the rest of what the server says about
     * components; `checks` is the group the panel keeps a copy of for its own live lints.
     *
     * @param  array<string, string>  $params
     * @return array{file: string, code: string, line: int|null, message: string}
     */
    private static function lint(string $file, string $code, ?int $line, array $params = [], string $group = 'checks'): array
    {
        return [
            'file' => $file,
            'code' => $code,
            'line' => $line,
            'message' => (string) __("webx-blocks::{$group}.{$code}", $params),
        ];
    }
}
