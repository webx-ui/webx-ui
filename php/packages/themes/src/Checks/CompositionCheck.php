<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Compositions\Finding;
use WebxUi\Compositions\Inspector;
use WebxUi\Compositions\Layers;
use WebxUi\Compositions\Probe;
use WebxUi\Compositions\Source;
use WebxUi\Compositions\Tree;
use WebxUi\Themes\Contracts\ThemeCheckPart;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeColor;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\Tokens;

/**
 * The compositions in `webx:theme:check` (COMPOSITIONS §8): every file of every layer read and
 * every composition × handle built over the chain checked, the way the compiler would — and what
 * the compiler would only log is a finding here, so a patch that outlived a rename stops a build
 * instead of quietly doing nothing on the site.
 *
 * Findings: a file that is not JSON or breaks the schema, a tree that cannot be built, what the
 * patcher skips (an id that is not there, a duplicate, a cycle, a protected node, a reserved
 * attribute), a file for a handle no module names, a composition nobody has a base for, a node of
 * a theme with a module's prefix, a `place` on a container that is not there, a theme-color token
 * the chain has no colour for. A note: a view of the chain or the site that stands in for a
 * composition's dispatcher — the copy the composition was made to replace (§6.4) —, a view or a
 * directive of the base or a module the site has not installed, a page composition's file for
 * the handle of its own name (§4), and a view a theme put in a composition that reads a variable
 * the base does not give its leaves (`vars`, §2.2).
 */
final class CompositionCheck implements ThemeCheckPart
{
    public function __construct(private readonly Application $app) {}

    public function check(ThemeChain $chain, array $viewPaths): array
    {
        $layers = $this->app->make(Layers::class)->withThemes(static fn (): array => array_map(static fn (ThemeManifest $layer): array => [
            'name' => $layer->name,
            'dir' => $layer->path.'/compositions',
            'local' => $layer->local,
        ], array_reverse($chain->layers)));

        $inspector = new Inspector($layers, new Probe($this->app['view'], $this->app['blade.compiler']));
        $lines = [];

        foreach ($inspector->unreadable() as $failure) {
            $lines[] = ['file' => $failure['file'], 'line' => null, 'message' => $this->strip($failure['file'], $failure['message'])];
        }

        if ($lines !== []) {
            // Nothing built over a file that cannot be read would say anything true.
            return $lines;
        }

        // A view, a component or a directive is looked up among the site's: only the site's own
        // chain has them, a theme checked by its path is read without them.
        $site = $chain === $this->app->make(ThemeChain::class);
        $compositions = $inspector->compositions();
        $trees = [];
        $findings = [];

        foreach ($inspector->all() as $pair) {
            if ($pair['tree'] === null) {
                $lines[] = ['file' => '', 'line' => null, 'message' => "{$pair['composition']} × {$pair['handle']} does not build — {$pair['error']}"];

                continue;
            }

            $trees[$pair['composition']][$pair['handle']] = $pair['tree'];

            foreach ($inspector->said($pair['tree']) as $finding) {
                if ($site || ! Inspector::emitted($finding)) {
                    $findings[(string) $finding] = $finding;
                }
            }
        }

        foreach ($findings as $finding) {
            $lines[] = ['file' => $finding->file, 'line' => null, 'message' => "{$finding->operation} — {$finding->message}", 'note' => $finding->note];
        }

        array_push(
            $lines,
            ...$this->files($inspector, $layers, $compositions),
            ...$this->themeColors($trees, $chain),
            ...$this->overrides($inspector, $compositions, $viewPaths),
            ...$this->places($trees, $viewPaths),
            ...$this->variables($inspector, $trees, $viewPaths, $site),
        );

        return $lines;
    }

    /**
     * What the files say apart from any tree: a handle no module names, a composition no layer
     * has a base for, a theme's node under a module's prefix.
     *
     * @param  list<string>  $compositions
     * @return list<array{file: string, line: int|null, message: string}>
     */
    private function files(Inspector $inspector, Layers $layers, array $compositions): array
    {
        $sources = $inspector->builder()->sources();
        $handles = ['default' => true];

        foreach ($layers->declaredHandles() as $declared) {
            foreach ($declared as $handle) {
                $handles[$handle] = true;
            }
        }

        foreach (array_keys($layers->layouts()) as $handle) {
            $handles[$handle] = true;
        }

        $prefixes = [];

        foreach ($sources as $source) {
            if ($source->kind === Source::MODULE) {
                $prefixes[(string) preg_replace('/^module-/', '', $source->label)] = $source->label;
            }
        }

        $lines = [];

        foreach ($sources as $source) {
            foreach ($source->compositions() as $composition) {
                if (! in_array($composition, $compositions, true)) {
                    $lines[] = ['file' => $source->where($composition, 'default'), 'line' => null, 'message' => "no layer has a base for the composition \"{$composition}\": its files never apply"];

                    continue;
                }

                foreach ($source->handles($composition) as $handle) {
                    $file = $source->where($composition, $handle);

                    if ($handle === $composition) {
                        // A page composition is included with `default` (§4): the handle is the
                        // composition itself, a second file for it says what default.json says.
                        $lines[] = ['file' => $file, 'line' => null, 'note' => true, 'message' => "the composition {$composition} is the page of {$handle} already: its handle is default — put this patch in {$composition}/default.json, the page never reads {$handle}.json"];

                        continue;
                    }

                    if (! isset($handles[$handle])) {
                        $lines[] = ['file' => $file, 'line' => null, 'message' => "unknown handle \"{$handle}\": no module names this page type (".implode(', ', array_keys($handles)).'), the file never applies'];
                    }

                    if ($source->kind === Source::MODULE) {
                        continue;
                    }

                    foreach ($this->added((array) ($source->read($composition, $handle)['add'] ?? [])) as $id) {
                        $prefix = strstr($id, '.', true);

                        if ($prefix !== false && isset($prefixes[$prefix])) {
                            $lines[] = ['file' => $file, 'line' => null, 'message' => "add {$id} — \"{$prefix}.\" is the prefix of {$prefixes[$prefix]}: name the node of a theme without it, the module's next node may take the id"];
                        }
                    }
                }
            }
        }

        return $lines;
    }

    /**
     * The ids a file adds, the children of what it adds too.
     *
     * @param  array<mixed>  $nodes
     * @return list<string>
     */
    private function added(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (isset($node['id']) && is_string($node['id'])) {
                $ids[] = $node['id'];
            }

            array_push($ids, ...$this->added((array) ($node['children'] ?? [])));
        }

        return $ids;
    }

    /**
     * `@webxThemeColor('…')` with a token the chain has no colour for prints no tag at all (§7).
     *
     * @param  array<string, array<string, Tree>>  $trees
     * @return list<array{file: string, line: int|null, message: string}>
     */
    private function themeColors(array $trees, ThemeChain $chain): array
    {
        if ($chain->isEmpty()) {
            return [];
        }

        $tokens = new Tokens($chain);
        $lines = [];

        foreach ($trees as $composition => $handles) {
            foreach ($handles as $tree) {
                foreach ($tree->root->walk() as $node) {
                    if ($node->kind !== 'directive' || $node->value !== 'webxThemeColor') {
                        continue;
                    }

                    $args = trim((string) $node->args);
                    $token = $args === '' ? ThemeColor::TOKEN : trim($args, '\'"');

                    if (preg_match('/^[a-z0-9-]+$/', $token) !== 1 || ($args !== '' && $token === $args)) {
                        $message = "{$node->id}: @webxThemeColor takes the name of a token in quotes ('color-surface'), not {$args} — a colour of your own is an html node";
                    } else {
                        $value = $tokens->resolved($token);
                        $message = $value === null
                            ? "{$node->id}: no token \"{$token}\" with a value in the chain — the page prints no theme-color"
                            : ($tokens->vocabulary()->accepts($token, $value) && str_starts_with($token, 'color-') ? null : "{$node->id}: \"{$token}\" is not a colour token — the page prints no theme-color");
                    }

                    if ($message !== null) {
                        $lines["{$composition}:{$message}"] = ['file' => $this->changedBy($tree, $node->id) ?? $node->origin, 'line' => null, 'message' => $message];
                    }
                }
            }
        }

        return array_values($lines);
    }

    /**
     * A view the site or the chain reads before a composition's dispatcher: the composition is
     * not printed there, and what the themes' files patch never shows.
     *
     * @param  list<string>  $compositions
     * @param  list<string>  $viewPaths
     * @return list<array{file: string, line: int|null, message: string, note: true}>
     */
    private function overrides(Inspector $inspector, array $compositions, array $viewPaths): array
    {
        $lines = [];

        foreach ($compositions as $composition) {
            $dispatcher = $inspector->builder()->contract($composition)['dispatcher'];
            $place = '<x-webx-composition::place id=\"body\" only=\"added\" />';

            if ($dispatcher !== null) {
                // The base says which view includes it (§2.2): its copy is the one to look for.
                $relatives = [self::relative($dispatcher)];
            } else {
                // A component of a theme (`layout` → components/layout), or the view of a module's
                // page a site copied over its namespace (`blog.article` → vendor/webx-blog/article).
                $relatives = ['components/'.str_replace('.', '/', $composition).'.blade.php'];

                if (str_contains($composition, '.')) {
                    [$module, $view] = explode('.', $composition, 2);
                    $relatives[] = "vendor/webx-{$module}/".str_replace('.', '/', $view).'.blade.php';
                }
            }

            foreach ($relatives as $relative) {
                foreach ($viewPaths as $directory) {
                    $file = rtrim($directory, '/').'/'.$relative;

                    if (! is_file($file)) {
                        continue;
                    }

                    if (str_starts_with($relative, 'vendor/')) {
                        $place = "<x-webx-composition::place composition=\"{$composition}\" id=\"…\" only=\"added\" />";
                    }

                    if (! str_contains((string) file_get_contents($file), "composition_view('{$composition}'")) {
                        $lines[] = ['file' => $file, 'line' => null, 'note' => true, 'message' => "overrides the composition {$composition}: compositions/{$composition}/*.json of the themes and the modules' pieces are not printed here. A patch replaces the copy — php artisan webx:composition:show {$composition} --blade prints what the composition gives — or keep it and bring the pieces with {$place}"];
                    }

                    break;
                }
            }
        }

        return $lines;
    }

    /**
     * `<x-webx-composition::place id="…">` in the views read: a container the final tree has.
     *
     * @param  array<string, array<string, Tree>>  $trees
     * @param  list<string>  $viewPaths
     * @return list<array{file: string, line: int|null, message: string}>
     */
    private function places(array $trees, array $viewPaths): array
    {
        $lines = [];

        foreach ($viewPaths as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            /** @var SplFileInfo $info */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $info) {
                $file = str_replace('\\', '/', $info->getPathname());
                $source = str_ends_with($file, '.blade.php') ? (string) @file_get_contents($file) : '';

                if (! str_contains($source, 'webx-composition::place') || preg_match_all('/<x-webx-composition::place\b[^>]*>/', $source, $tags, PREG_OFFSET_CAPTURE) === false) {
                    continue;
                }

                foreach ($tags[0] as [$tag, $offset]) {
                    if (preg_match('/\sid="([^"]+)"/', $tag, $id) !== 1) {
                        continue;
                    }

                    $composition = preg_match('/\scomposition="([^"]+)"/', $tag, $match) === 1 ? $match[1] : 'layout';
                    $handle = preg_match('/\shandle="([^"]+)"/', $tag, $match) === 1 ? $match[1] : 'default';
                    $tree = $trees[$composition][$handle] ?? $trees[$composition]['default'] ?? null;
                    $line = substr_count($source, "\n", 0, $offset) + 1;

                    if ($tree === null) {
                        $lines[] = ['file' => $file, 'line' => $line, 'message' => "place: no composition \"{$composition}\""];
                    } elseif (! ($tree->find($id[1])?->isContainer() ?? false)) {
                        $lines[] = ['file' => $file, 'line' => $line, 'message' => "place: no container \"{$id[1]}\" in {$composition} × {$handle} — the page fails to render"];
                    }
                }
            }
        }

        return $lines;
    }

    /**
     * A view a theme or the site put in a composition — added or in place of a node — that reads a
     * variable the base does not give its leaves (`vars`, §2.2) nor the node its `with`: on the page
     * it is an undefined variable. Read from the source, not rendered: a guess, so a note — the
     * variables a view sets itself, loops and closures, the shared ones and Blade's own are not
     * counted. Only where the base says its `vars`.
     *
     * @param  array<string, array<string, Tree>>  $trees
     * @param  list<string>  $viewPaths
     * @return list<array{file: string, line: int|null, message: string, note: true}>
     */
    private function variables(Inspector $inspector, array $trees, array $viewPaths, bool $site): array
    {
        $modules = array_map(static fn (Source $source): string => $source->label, array_filter($inspector->builder()->sources(), static fn (Source $source): bool => $source->kind === Source::MODULE));
        $shared = array_keys($this->app['view']->getShared());
        $lines = [];

        foreach ($trees as $composition => $handles) {
            foreach ($handles as $tree) {
                if ($tree->vars === []) {
                    continue;
                }

                foreach ($tree->root->walk() as $node) {
                    if ($node->kind !== 'view' || $node->base || in_array($node->origin, $modules, true)) {
                        continue;
                    }

                    $file = $this->viewFile($node->value, $viewPaths, $site);

                    if ($file === null) {
                        continue;
                    }

                    $missing = array_values(array_diff(self::reads((string) file_get_contents($file)), $tree->vars, array_keys($node->with), $shared));

                    if ($missing !== []) {
                        $given = implode(', ', array_map(static fn (string $var): string => '$'.$var, $tree->vars));
                        $lines["{$composition}:{$node->id}:{$node->value}"] = ['file' => $this->changedBy($tree, $node->id) ?? $node->origin, 'line' => null, 'note' => true, 'message' => "{$node->id}: the view {$node->value} reads ".implode(', ', array_map(static fn (string $var): string => '$'.$var, $missing))." — the leaves of {$composition} get {$given}: take it from them or give it with \"with\""];
                    }
                }
            }
        }

        return array_values($lines);
    }

    /**
     * Where a view is: the views read first, then — on the site's own chain — the site's finder.
     *
     * @param  list<string>  $viewPaths
     */
    private function viewFile(string $view, array $viewPaths, bool $site): ?string
    {
        if (! str_contains($view, '::')) {
            foreach ($viewPaths as $directory) {
                $file = rtrim($directory, '/').'/'.str_replace('.', '/', $view).'.blade.php';

                if (is_file($file)) {
                    return $file;
                }
            }
        }

        if (! $site) {
            return null;
        }

        try {
            return $this->app['view']->getFinder()->find($view);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The variables a Blade source reads and does not set: `$x = …`, `as $k => $v`, the parameters
     * of a closure, and Blade's own (`$loop`, `$slot`, `$__env`…) are left out.
     *
     * @return list<string>
     */
    private static function reads(string $source): array
    {
        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', $source, $used);

        $set = ['this', 'loop', 'slot', 'attributes', 'component', 'errors', 'app'];
        preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)\s*=(?![=>])/', $source, $assigned);
        preg_match_all('/\bas\s+\$([A-Za-z_][A-Za-z0-9_]*)(?:\s*=>\s*\$([A-Za-z_][A-Za-z0-9_]*))?/', $source, $loops);
        preg_match_all('/\b(?:fn|function)\s*\(([^)]*)\)/', $source, $closures);
        preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', implode(' ', $closures[1]), $parameters);

        $set = [...$set, ...$assigned[1], ...$loops[1], ...array_filter($loops[2]), ...$parameters[1]];

        return array_values(array_unique(array_filter($used[1], static fn (string $var): bool => ! str_starts_with($var, '__') && ! in_array($var, $set, true))));
    }

    /** A view name as a path under a directory of views: `webx-blog::article` → vendor/webx-blog/article.blade.php. */
    private static function relative(string $view): string
    {
        if (str_contains($view, '::')) {
            [$namespace, $view] = explode('::', $view, 2);

            return "vendor/{$namespace}/".str_replace('.', '/', $view).'.blade.php';
        }

        return str_replace('.', '/', $view).'.blade.php';
    }

    /** The last file that put a node where it is — a replace or an add — for a finding about it. */
    private function changedBy(Tree $tree, string $id): ?string
    {
        $file = null;

        foreach ($tree->changes as $change) {
            if ($change['id'] === $id && in_array($change['operation'], ['add', 'replace'], true)) {
                $file = $change['file'];
            }
        }

        return $file;
    }

    /** The reader's message names the file already; the line names it once. */
    private function strip(string $file, string $message): string
    {
        return str_starts_with($message, $file.': ') ? substr($message, strlen($file) + 2) : $message;
    }
}
