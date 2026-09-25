<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\HtmlString;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\Exceptions\BlockNotPublishable;
use WebxUi\Blocks\Exceptions\BlocksException;

/**
 * An entity's content — a tree of `{ key, type, values }` nodes — printed as HTML.
 *
 * One renderer for everything: the live page, the preview in the panel and the "open on the
 * site" link all run this code. What the preview changes is which version of a type it takes
 * (the draft), how a failure is shown (a notice in place of the block rather than a line in the
 * log), and a pair of comments around every block so the panel can find and replace it.
 *
 * Every block renders inside its own try/catch. Without that, a typo in one template takes the
 * whole document with it, and we hear about it from a visitor.
 */
final class Renderer
{
    private bool $preview = false;

    /** @var array<string, BlockType> slug → the type, at the version, this response has printed */
    private array $used = [];

    /**
     * The templates being evaluated right now, outermost first, each with how many times it has
     * called each type so far. A tag reads the top to know where it was called from — the entity,
     * the depth, the key — without anybody passing `$block` to it, and the whole of it to know
     * whether it is calling itself.
     *
     * @var list<array{context: BlockContext, calls: array<string, int>}>
     */
    private array $stack = [];

    /** @var array<string, int> the same count for tags called from outside any block: a site's view */
    private array $rootCalls = [];

    /**
     * Types to take instead of their published versions: the draft of a child, while its
     * parents are checked against it before it is published (§3.6 of the components spec).
     *
     * @var array<string, BlockType>
     */
    private array $substitutes = [];

    /**
     * A check is running: a called component that fails fails the check rather than leaving a
     * gap — the gap is what the check is there to prevent.
     */
    private bool $strict = false;

    public function __construct(
        private readonly BlockTypes $types,
        private readonly TemplateCompiler $compiler,
        private readonly ViewFactory $views,
        private readonly Config $config,
        private readonly Values $values,
    ) {}

    /**
     * The blocks of an entity, top to bottom.
     *
     * @param  iterable<array-key, mixed>|null  $blocks
     */
    public function render(?iterable $blocks, ?object $entity = null): HtmlString
    {
        return new HtmlString($this->list($blocks, $entity, 0));
    }

    /**
     * What `@blocks('content')` prints: the blocks held in one field of the block being
     * rendered, one level deeper.
     */
    public function nested(BlockContext $parent, string $field): HtmlString
    {
        $depth = $parent->depth + 1;

        if ($depth >= $this->maxDepth()) {
            $this->problem(
                $parent->key,
                $parent->type,
                sprintf('Blocks nest deeper than the limit of %d levels; the ones inside "%s" are left out.', $this->maxDepth(), $field),
            );

            return new HtmlString('');
        }

        $children = $parent->values[$field] ?? null;

        return new HtmlString(is_iterable($children) ? $this->list($children, $parent->entity, $depth) : '');
    }

    /**
     * Render the type on its sample values — or on the values handed in — and say why when it
     * cannot be.
     *
     * This is the gate before publishing: a template that does not compile or throws on its
     * own sample stays a draft, and the panel runs it on every page's values as well.
     *
     * A component the template calls is rendered too, and its failure is the check's failure,
     * reported with the component's own line. `$substitutes` are types to call instead of their
     * published versions — the draft of a child, while each of its parents is checked on it.
     *
     * @param  array<string, mixed>|null  $values  The sample when null.
     * @param  array<string, BlockType>  $substitutes  Slug → the type to call in its place.
     *
     * @throws BlockNotPublishable
     */
    public function check(BlockType $type, ?array $values = null, array $substitutes = []): void
    {
        $context = new BlockContext(
            key: 'sample',
            type: $type->slug,
            version: $type->version,
            values: $this->values->resolve($type, $values ?? $type->sample),
            entity: null,
            depth: 0,
        );

        $path = $this->compiler->path($type);

        $wasStrict = $this->strict;
        $wasSubstitutes = $this->substitutes;
        $this->strict = true;
        $this->substitutes = $substitutes;

        try {
            $this->evaluate($path, $type, $context);
        } catch (BlockNotPublishable $failure) {
            // A component this template calls, failing in its own template: its line is the
            // one worth showing, and it already carries it.
            throw $failure;
        } catch (Throwable $failure) {
            throw BlockNotPublishable::because($type, $failure, $this->line($failure, $path, $type));
        } finally {
            $this->strict = $wasStrict;
            $this->substitutes = $wasSubstitutes;
        }
    }

    /**
     * What `<x-webx-block type="…">` prints: a type called from a template — another block's, a
     * module's view, the site's layout.
     *
     * Where it was called from is the top of the stack: the entity is that template's, the depth
     * one deeper, the key the parent's key and the call's place in it. The type is looked up the
     * way content looks it up — the published version, the draft under a preview token — so a
     * draft of a component shows in the preview of any page that calls it and nowhere else.
     *
     * No type, or none published, prints `$fallback` with the same values and slots; without
     * one it is a gap on the site and a notice in the preview, like an unknown type in content.
     * A failure is caught here, as a block's is: one card that throws leaves a hole in the grid,
     * not an empty page. No markers around it: the panel swaps nodes of content, and a call is
     * part of its parent's template — it goes when the parent is swapped.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, Htmlable>  $slots  `slot` and the named ones, rendered by the caller.
     */
    public function tag(string $slug, array $values = [], array $slots = [], ?string $fallback = null): HtmlString
    {
        $parent = $this->top();
        $depth = $parent === null ? 0 : $parent->depth + 1;
        $key = $this->callKey($slug);
        $chain = array_map(static fn (array $frame): string => $frame['context']->type, $this->stack);

        if (in_array($slug, $chain, true)) {
            $through = array_slice($chain, (int) array_search($slug, $chain, true) + 1);
            $message = $through === []
                ? sprintf('"%s" calls itself; the call is left out.', $slug)
                : sprintf('"%s" calls itself through "%s"; the call is left out.', $slug, implode('" → "', $through));

            return new HtmlString($this->refuse($key, $slug, $message));
        }

        if ($depth >= $this->maxDepth()) {
            return new HtmlString($this->refuse(
                $key,
                $slug,
                sprintf('Blocks nest deeper than the limit of %d levels; the call to "%s" is left out.', $this->maxDepth(), $slug),
            ));
        }

        $type = $this->substitutes[$slug] ?? ($this->preview ? $this->types->draft($slug) : $this->types->find($slug));

        if (! $type instanceof BlockType) {
            if ($fallback !== null) {
                return new HtmlString($this->views->make($fallback, [...$values, ...$this->slotData($slots)])->render());
            }

            return new HtmlString($this->problem($key, $slug, "There is no published block type \"{$slug}\"; the call is left out."));
        }

        $this->used[$type->slug] = $type;

        $entity = $parent?->entity;

        $context = new BlockContext(
            key: $key,
            type: $type->slug,
            version: $type->version,
            values: $this->values->resolve($type, $values, $entity),
            entity: $entity,
            depth: $depth,
        );

        $path = $this->compiler->path($type);

        try {
            $html = $this->evaluate($path, $type, $context, $slots);
        } catch (Throwable $failure) {
            if ($this->strict) {
                throw $failure instanceof BlockNotPublishable
                    ? $failure
                    : BlockNotPublishable::because($type, $failure, $this->line($failure, $path, $type));
            }

            $html = $this->failed($context, $failure, $this->line($failure, $path, $type));
        }

        return new HtmlString($html);
    }

    /**
     * One block for the panel: the type at the version given, on the values given, as the
     * preview would draw it — a notice with the line in place of a failure, the marker pair
     * around it, drafts for whatever it nests. `$unsaved` compiles the template in the type
     * as it is rather than the stored version, for the editor rendering what is being typed.
     *
     * @param  array<string, mixed>  $values
     */
    public function draw(BlockType $type, array $values, string $key = 'sample', bool $unsaved = false): string
    {
        $context = new BlockContext(
            key: $key,
            type: $type->slug,
            version: $type->version,
            values: $this->values->resolve($type, $values),
            entity: null,
            depth: 0,
        );

        $path = $unsaved ? $this->compiler->adHoc($type->slug, $type->template) : $this->compiler->path($type);
        $was = $this->preview;
        $this->preview = true;

        try {
            $html = $this->evaluate($path, $type, $context);
        } catch (Throwable $failure) {
            $html = $this->failed($context, $failure, $this->line($failure, $path, $type));
        } finally {
            $this->preview = $was;
        }

        $this->used[$type->slug] = $type;

        return $this->markers($key, $html);
    }

    /**
     * Preview mode: drafts instead of published versions, notices instead of log lines,
     * and a marker pair around every block.
     */
    public function preview(bool $on = true): self
    {
        $this->preview = $on;

        return $this;
    }

    public function isPreview(): bool
    {
        return $this->preview;
    }

    /**
     * Which types, at which versions, this response has printed so far — what the bundle of
     * styles and scripts is built from.
     *
     * @return array<string, int>
     */
    public function used(): array
    {
        return array_map(static fn (BlockType $type): int => $type->version, $this->used);
    }

    /**
     * The same, as the types themselves — what the bundle is glued from.
     *
     * @return array<string, BlockType>
     */
    public function usedTypes(): array
    {
        return $this->used;
    }

    /** Between two responses of one process: the next one is not a preview until it says so. */
    public function flush(): void
    {
        $this->used = [];
        $this->preview = false;
        $this->stack = [];
        $this->rootCalls = [];
        $this->substitutes = [];
        $this->strict = false;
    }

    /**
     * @param  iterable<array-key, mixed>|null  $blocks
     */
    private function list(?iterable $blocks, ?object $entity, int $depth): string
    {
        if ($blocks === null) {
            return '';
        }

        $html = '';
        $index = 0;

        foreach ($blocks as $node) {
            /*
             * The one place a switched-off block is left out (§23), and it is here rather than
             * in `one()` on purpose: nothing below this line runs for it, so its type never
             * joins `used[]` and its styles and script stay off the page. Its children go with
             * it — the recursion simply never reaches them — while their own flags stay in the
             * content untouched.
             */
            if (is_array($node) && is_string($node['type'] ?? null) && $node['type'] !== '' && ! Content::isHidden($node)) {
                $html .= $this->one($node, $entity, $depth, $index);
            }

            $index++;
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function one(array $node, ?object $entity, int $depth, int $index): string
    {
        $slug = (string) $node['type'];
        $key = is_string($node['key'] ?? null) && $node['key'] !== '' ? $node['key'] : "{$depth}-{$index}";
        $values = is_array($node['values'] ?? null) ? $node['values'] : [];

        $type = $this->preview ? $this->types->draft($slug) : $this->types->find($slug);

        if (! $type instanceof BlockType) {
            return $this->wrap($key, $this->problem($key, $slug, "There is no published block type \"{$slug}\"; the block is left out."));
        }

        $this->used[$type->slug] = $type;

        $context = new BlockContext(
            key: $key,
            type: $type->slug,
            version: $type->version,
            values: $this->values->resolve($type, $values, $entity),
            entity: $entity,
            depth: $depth,
        );

        $path = $this->compiler->path($type);

        try {
            $html = $this->evaluate($path, $type, $context);
        } catch (Throwable $failure) {
            $html = $this->failed($context, $failure, $this->line($failure, $path, $type));
        }

        return $this->wrap($key, $html);
    }

    /**
     * The schema's fields become variables (`$title`), filled from the values and null where
     * the values have nothing — so a field added to a block after the content was written is
     * null on the old pages, not an error that blanks them. A variable the schema does not
     * declare stays undefined, which is what refuses a template at publishing time.
     *
     * The values are the resolved ones ({@see Values}): the block that holds a picture is
     * holding its address by now, and `$block->values` — what a template hands its script —
     * says the same thing the variables do.
     *
     * `$block` and `$entity` come next, and the view factory's shared data — `$__env` above
     * all — last, so a field called `app` cannot take the application's place. A value whose
     * name is not a valid variable name is simply not extracted, and is read as
     * `$block->value('project-name')`.
     *
     * Slots come after the values: `$slot` always exists — empty when the tag closed itself, and
     * in every template that was never called at all — and a named one the caller passed takes
     * the place of the empty one its `wx-slot` field resolved to.
     *
     * The context is on the stack for exactly as long as its template runs, so a tag inside it
     * knows its parent and one after it does not.
     *
     * @param  array<string, Htmlable>  $slots
     */
    private function evaluate(string $path, BlockType $type, BlockContext $context, array $slots = []): string
    {
        $data = array_fill_keys($type->fields(), null);

        foreach ($context->values as $name => $value) {
            $data[$name] = $value;
        }

        foreach ($this->slotData($slots) as $name => $slot) {
            $data[$name] = $slot;
        }

        $data['block'] = $context;
        $data['entity'] = $context->entity;

        foreach ($this->views->getShared() as $name => $value) {
            $data[$name] = $value;
        }

        $this->stack[] = ['context' => $context, 'calls' => []];

        try {
            return $this->views->getEngineResolver()->resolve('php')->get($path, $data);
        } finally {
            array_pop($this->stack);
        }
    }

    /**
     * @param  array<string, Htmlable>  $slots
     * @return array<string, Htmlable>
     */
    private function slotData(array $slots): array
    {
        return ['slot' => new HtmlString(''), ...$slots];
    }

    private function top(): ?BlockContext
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)]['context'];
    }

    /**
     * The key of a call: the parent's key, then the type and which call of it this is —
     * `k1/recipe-card-3`. Counted per template being evaluated, so the same card in the same
     * place has the same key on every render.
     */
    private function callKey(string $slug): string
    {
        if ($this->stack === []) {
            $number = $this->rootCalls[$slug] = ($this->rootCalls[$slug] ?? 0) + 1;

            return "{$slug}-{$number}";
        }

        $top = array_key_last($this->stack);
        $number = $this->stack[$top]['calls'][$slug] = ($this->stack[$top]['calls'][$slug] ?? 0) + 1;

        return $this->stack[$top]['context']->key."/{$slug}-{$number}";
    }

    /**
     * A call that cannot be made at all — a cycle, too deep. In a check that is the check's
     * failure; anywhere else it is a problem like any other.
     */
    private function refuse(string $key, string $slug, string $message): string
    {
        if ($this->strict) {
            throw new BlocksException($message);
        }

        return $this->problem($key, $slug, $message);
    }

    /**
     * A block that threw. Live, that is a line in the log and nothing on the page — the rest
     * of the document is more useful than an error in the middle of it. In the preview it is
     * a notice where the block would be, with the line, so the author sees it right away.
     */
    private function failed(BlockContext $context, Throwable $failure, ?int $line): string
    {
        if (! $this->preview) {
            Container::getInstance()->make(ExceptionHandler::class)->report($failure);

            return '';
        }

        $where = $line === null ? '' : " (line {$line})";

        return $this->notice($context->key, $context->type, $failure->getMessage().$where);
    }

    /** Something to say about a block that is not an exception: an unknown type, nesting too deep. */
    private function problem(string $key, string $slug, string $message): string
    {
        if (! $this->preview) {
            Container::getInstance()->make(LoggerInterface::class)->warning($message, ['block' => $slug, 'key' => $key]);

            return '';
        }

        return $this->notice($key, $slug, $message);
    }

    private function notice(string $key, string $slug, string $message): string
    {
        return sprintf(
            '<div class="wx-block-error" data-wx-block-error="%s" data-wx-block="%s"><strong>%s</strong> %s</div>',
            e($key),
            e($slug),
            e($slug),
            e($message),
        );
    }

    /**
     * The preview's markers. Nothing on the live site: the panel is the only reader, and a
     * comment per block is a comment per block.
     */
    private function wrap(string $key, string $html): string
    {
        if (! $this->preview) {
            return $html;
        }

        return $this->markers($key, $html);
    }

    private function markers(string $key, string $html): string
    {
        $key = (string) preg_replace('/[^A-Za-z0-9_.:-]/', '', $key);

        return "<!--wx:{$key}-->{$html}<!--/wx:{$key}-->";
    }

    /**
     * The template's line the failure came from, when it came from the template at all.
     *
     * The line in the exception is the compiled file's, and Blade does not keep the count —
     * it doubles the newline after every echo so that PHP, which eats one, prints one. The
     * compiler maps it back.
     */
    private function line(Throwable $failure, string $path, BlockType $type): ?int
    {
        $path = str_replace('\\', '/', $path);
        $compiled = null;

        if (str_replace('\\', '/', $failure->getFile()) === $path) {
            $compiled = $failure->getLine();
        } else {
            foreach ($failure->getTrace() as $frame) {
                if (isset($frame['file'], $frame['line']) && str_replace('\\', '/', (string) $frame['file']) === $path) {
                    $compiled = (int) $frame['line'];

                    break;
                }
            }
        }

        return $compiled === null ? null : $this->compiler->templateLine($type->template, $compiled);
    }

    private function maxDepth(): int
    {
        return max(1, (int) $this->config->get('webx-blocks.max_depth', 5));
    }
}
