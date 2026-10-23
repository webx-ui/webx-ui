<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Console;

use Illuminate\Console\Command;
use WebxUi\Compositions\Exceptions\CompositionException;
use WebxUi\Compositions\Finding;
use WebxUi\Compositions\Inspector;
use WebxUi\Compositions\Node;
use WebxUi\Compositions\Tree;

/**
 * The final tree of a composition and who made it so (COMPOSITIONS §8): without an argument the
 * compositions, their handles and the files of each layer; with one, the tree of a handle — and
 * with `--origin` the layer that put each node there and every file that touched it since. Read
 * from the sources, not the compiled set: what the next compile gives, not what the last one did.
 */
final class ShowCommand extends Command
{
    protected $signature = 'webx:composition:show
        {composition? : The composition to show the tree of: layout, layout.minimal, …}
        {--handle=default : The page type: blog.article, services.show, …}
        {--origin : Say who put each node there and who changed it}
        {--blade : Print the Blade the compiler writes for it}
        {--json : The same as JSON, the origin always in it}';

    protected $description = 'Show the compositions, or the final tree of one with who added, moved, replaced, removed or changed each node';

    public function handle(Inspector $inspector): int
    {
        $composition = $this->argument('composition');
        $handle = (string) $this->option('handle');

        try {
            if (! is_string($composition) || $composition === '') {
                return $this->overview($inspector);
            }

            if (! in_array($composition, $inspector->compositions(), true)) {
                $this->components->error("No layer has a base for the composition \"{$composition}\". Known: ".(implode(', ', $inspector->compositions()) ?: 'none').'.');

                return self::FAILURE;
            }

            if ($this->option('blade')) {
                $this->output->write($inspector->blade($composition, $handle));

                return self::SUCCESS;
            }

            $tree = $inspector->tree($composition, $handle);
        } catch (CompositionException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $removed = $this->removed($tree);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'composition' => $composition,
                'handle' => $handle,
                'layers' => $tree->layers,
                'vars' => $tree->vars,
                'dispatcher' => $tree->dispatcher,
                'tree' => $this->node($tree->root, $tree),
                'removed' => $removed,
                'findings' => array_map(static fn (Finding $finding): array => $finding->toArray(), $tree->findings),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line("<options=bold>{$composition} × {$handle}</> — from ".implode(', ', $tree->layers));
        $this->contract($tree->vars, $tree->dispatcher);
        $this->newLine();

        $rows = [];
        $this->rows($tree->root, $tree, 0, $rows);
        $widths = [0, 0];

        foreach ($rows as $row) {
            $widths = [max($widths[0], mb_strlen($row[0])), max($widths[1], mb_strlen($row[1]))];
        }

        foreach ($rows as [$id, $what, $origin]) {
            $this->line(rtrim($this->pad($id, $widths[0] + 2).($origin === '' ? $what : $this->pad($what, $widths[1] + 2).$origin)));
        }

        if ($removed !== []) {
            $this->newLine();
            $this->line('removed: '.implode('; ', array_map(static fn (array $gone): string => "{$gone['id']} ← {$gone['file']}", $removed)));
        }

        $this->findings($tree->findings);

        return self::SUCCESS;
    }

    private function overview(Inspector $inspector): int
    {
        $compositions = [];

        foreach ($inspector->compositions() as $composition) {
            $files = [];

            foreach ($inspector->builder()->sources() as $source) {
                foreach ($source->handles($composition) as $handle) {
                    $files[] = ['layer' => $source->label, 'kind' => $source->kind, 'handle' => $handle, 'file' => $source->where($composition, $handle)];
                }
            }

            $contract = $inspector->builder()->contract($composition);
            $compositions[] = [
                'name' => $composition,
                'extends' => $inspector->builder()->parent($composition),
                'vars' => $contract['vars'],
                'dispatcher' => $contract['dispatcher'],
                'handles' => $inspector->handles($composition),
                'files' => $files,
            ];
        }

        $declared = $inspector->layers()->declaredHandles();
        $unreadable = $inspector->unreadable();
        $findings = $unreadable === [] ? $inspector->findings() : [];

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'compositions' => $compositions,
                'declared_handles' => $declared,
                'layouts' => $inspector->layers()->layouts(),
                'unreadable' => $unreadable,
                'findings' => array_map(static fn (Finding $finding): array => $finding->toArray(), $findings),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $unreadable === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($compositions === []) {
            $this->components->info('No layer has a base for a composition.');
        }

        foreach ($compositions as $composition) {
            $this->line("<options=bold>{$composition['name']}</>".($composition['extends'] === null ? '' : " extends {$composition['extends']}").' — handles: '.implode(', ', $composition['handles']));
            $this->contract($composition['vars'], $composition['dispatcher'], '  ');

            foreach ($composition['files'] as $file) {
                $this->line("  {$file['file']}  <fg=gray>{$file['kind']} {$file['layer']}</>");
            }
        }

        if ($declared !== []) {
            $this->newLine();
            $this->line('Page types the modules name: '.implode(', ', array_merge(...array_values($declared))));
        }

        foreach ($unreadable as $failure) {
            $this->components->error($failure['message']);
        }

        $this->findings($findings);

        return $unreadable === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Each node as a line: its id indented by depth, what it is, and with `--origin` who.
     *
     * @param  list<array{string, string, string}>  $rows
     */
    private function rows(Node $node, Tree $tree, int $depth, array &$rows, string $prefix = ''): void
    {
        $origin = '';

        if ($this->option('origin')) {
            $changes = array_map(static fn (array $change): string => "{$change['operation']} ← {$change['file']}", $this->changes($node, $tree));
            $origin = implode('; ', [$node->origin, ...$changes]);
        }

        $rows[] = [str_repeat('  ', $depth).$prefix.$node->id, $this->what($node), $origin];

        foreach ($node->fallback as $fallback) {
            $this->rows($fallback, $tree, $depth + 1, $rows, 'fallback: ');
        }

        foreach ($node->children as $child) {
            $this->rows($child, $tree, $depth + 1, $rows);
        }
    }

    /**
     * What the leaves get and which view includes them, when the base says it.
     *
     * @param  list<string>  $vars
     */
    private function contract(array $vars, ?string $dispatcher, string $indent = ''): void
    {
        if ($vars !== []) {
            $this->line($indent.'vars: '.implode(', ', array_map(static fn (string $var): string => '$'.$var, $vars)));
        }

        if ($dispatcher !== null) {
            $this->line("{$indent}dispatcher: {$dispatcher}");
        }
    }

    /** str_pad by characters: an arrow or a letter of another script is one wide. */
    private function pad(string $text, int $width): string
    {
        return $text.str_repeat(' ', max(0, $width - mb_strlen($text)));
    }

    /** What a node prints, in a few words. */
    private function what(Node $node): string
    {
        $attrs = implode(' ', array_map(static fn (string $name, string|bool $value): string => $value === true ? $name : "{$name}=\"{$value}\"", array_keys($node->attrs), $node->attrs));

        return match ($node->kind) {
            'tag' => '<'.trim("{$node->value} {$attrs}").'>',
            'component' => '<x-'.trim("{$node->value} {$attrs}").'>',
            'view' => "view {$node->value}",
            'region' => "region {$node->value}".($node->regionFallback === null ? '' : " (fallback {$node->regionFallback})"),
            'slot' => "slot {$node->value}",
            'stack' => "@stack('{$node->value}')",
            'directive' => "@{$node->value}".($node->args === null ? '' : "({$node->args})"),
            'html' => 'html',
            default => $node->kind,
        };
    }

    /**
     * The changes of the tree to one node, but its own `add`: that one is the origin already.
     *
     * @return list<array{id: string, operation: string, file: string}>
     */
    private function changes(Node $node, Tree $tree): array
    {
        return array_values(array_filter($tree->changes, static fn (array $change): bool => $change['id'] === $node->id && $change['operation'] !== 'add'));
    }

    /**
     * The nodes some file removed, and which file.
     *
     * @return list<array{id: string, file: string}>
     */
    private function removed(Tree $tree): array
    {
        $removed = [];

        foreach ($tree->changes as $change) {
            if ($change['operation'] === 'remove' && ! $tree->has($change['id'])) {
                $removed[] = ['id' => $change['id'], 'file' => $change['file']];
            }
        }

        return $removed;
    }

    /** @return array<string, mixed> */
    private function node(Node $node, Tree $tree): array
    {
        $added = array_values(array_filter($tree->changes, static fn (array $change): bool => $change['id'] === $node->id && $change['operation'] === 'add'));

        return array_filter([
            'id' => $node->id,
            'kind' => $node->kind,
            'value' => $node->value === '' ? null : $node->value,
            'attrs' => $node->attrs === [] ? null : $node->attrs,
            'props' => $node->props === [] ? null : $node->props,
            'args' => $node->args,
            'fallback' => $node->kind === 'region' ? $node->regionFallback : ($node->fallback === [] ? null : array_map(fn (Node $child): array => $this->node($child, $tree), $node->fallback)),
            'protected' => $node->protected ?: null,
            'origin' => $node->origin,
            'base' => $node->base,
            'added_by' => $added === [] ? null : $added[array_key_last($added)]['file'],
            'changes' => array_map(static fn (array $change): array => ['operation' => $change['operation'], 'file' => $change['file']], $this->changes($node, $tree)),
            'children' => $node->isContainer() ? array_map(fn (Node $child): array => $this->node($child, $tree), $node->children) : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @param list<Finding> $findings */
    private function findings(array $findings): void
    {
        if ($findings === []) {
            return;
        }

        $this->newLine();

        foreach ($findings as $finding) {
            $finding->note ? $this->line("  note: {$finding}") : $this->components->warn((string) $finding);
        }
    }
}
