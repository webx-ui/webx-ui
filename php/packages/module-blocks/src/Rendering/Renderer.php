<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\HtmlString;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Exceptions\BlockNotPublishable;

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

    /** @var array<string, int> slug → version number of every type this response has printed */
    private array $used = [];

    public function __construct(
        private readonly BlockTypes $types,
        private readonly TemplateCompiler $compiler,
        private readonly ViewFactory $views,
        private readonly Config $config,
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
     * Render the type on its sample values, and say why when it cannot be.
     *
     * This is the gate before publishing: a template that does not compile or throws on its
     * own sample stays a draft.
     *
     * @throws BlockNotPublishable
     */
    public function check(BlockType $type): void
    {
        $context = new BlockContext(
            key: 'sample',
            type: $type->slug,
            version: $type->version,
            values: $type->sample,
            entity: null,
            depth: 0,
        );

        $path = $this->compiler->path($type);

        try {
            $this->evaluate($path, $type, $context);
        } catch (Throwable $failure) {
            throw BlockNotPublishable::because($type, $failure, $this->line($failure, $path, $type));
        }
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
        return $this->used;
    }

    /** Between two responses of one process. */
    public function flush(): void
    {
        $this->used = [];
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
            if (is_array($node) && is_string($node['type'] ?? null) && $node['type'] !== '') {
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

        $this->used[$type->slug] = $type->version;

        $context = new BlockContext(
            key: $key,
            type: $type->slug,
            version: $type->version,
            values: $values,
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
     * `$block` and `$entity` come next, and the view factory's shared data — `$__env` above
     * all — last, so a field called `app` cannot take the application's place. A value whose
     * name is not a valid variable name is simply not extracted, and is read as
     * `$block->value('project-name')`.
     */
    private function evaluate(string $path, BlockType $type, BlockContext $context): string
    {
        $data = array_fill_keys($type->fields(), null);

        foreach ($context->values as $name => $value) {
            $data[$name] = $value;
        }

        $data['block'] = $context;
        $data['entity'] = $context->entity;

        foreach ($this->views->getShared() as $name => $value) {
            $data[$name] = $value;
        }

        return $this->views->getEngineResolver()->resolve('php')->get($path, $data);
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
