<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use WebxUi\Blocks\Exceptions\BlockNotPublishable;
use WebxUi\Blocks\Exceptions\BlocksException;

/**
 * A publish check that failed, with where: the sample, a page named by model and id, a type
 * that calls this one (on its sample or on one of its pages), the place a module declared, or a
 * circle of calls. The controller turns it into a 422 the editor understands — the message under
 * the template with the line, and the page or the parent it broke on.
 */
final class PublishFailed extends BlocksException
{
    /**
     * @param  array{model: string, id: int|string, title: string|null}|null  $entity
     * @param  array{id: int, slug: string, title: string}|null  $parent
     * @param  list<string>|null  $cycle
     */
    private function __construct(
        public readonly BlockNotPublishable $failure,
        public readonly ?array $entity,
        public readonly ?array $parent = null,
        public readonly ?string $declared = null,
        public readonly ?array $cycle = null,
    ) {
        parent::__construct($failure->getMessage(), 0, $failure);
    }

    public static function onSample(BlockNotPublishable $failure): self
    {
        return new self($failure, null);
    }

    public static function onEntity(BlockNotPublishable $failure, string $model, int|string $id, ?string $title): self
    {
        return new self($failure, ['model' => $model, 'id' => $id, 'title' => $title]);
    }

    /**
     * A type that calls this one failed with this one's draft in it — on its own sample when
     * `$entity` is null, on that page's values otherwise.
     *
     * @param  array{id: int, slug: string, title: string}  $parent
     * @param  array{model: string, id: int|string, title: string|null}|null  $entity
     */
    public static function onParent(BlockNotPublishable $failure, array $parent, ?array $entity): self
    {
        return new self($failure, $entity, $parent);
    }

    /** The module's own place for this component failed on the module's sample data. */
    public static function onDeclared(BlockNotPublishable $failure, string $module): self
    {
        return new self($failure, null, declared: $module);
    }

    /**
     * @param  list<string>  $path
     */
    public static function onCycle(string $slug, int $version, array $path): self
    {
        return new self(
            new BlockNotPublishable($slug, $version, CallCycle::describe($path), null),
            null,
            cycle: $path,
        );
    }

    /**
     * The sentence for the editor, in the panel's language: the reason itself, with what it
     * broke in front of it when that was not the type's own sample.
     */
    public function describe(): string
    {
        $reason = $this->failure->reason;

        if ($this->cycle !== null) {
            return (string) __('webx-blocks::calls.publish-cycle', ['path' => CallCycle::describe($this->cycle)]);
        }

        if ($this->parent !== null) {
            return $this->entity === null
                ? (string) __('webx-blocks::calls.publish-breaks-parent', ['parent' => $this->parent['title'], 'reason' => $reason])
                : (string) __('webx-blocks::calls.publish-breaks-parent-on', [
                    'parent' => $this->parent['title'],
                    'entity' => $this->entity['title'] ?? '#'.$this->entity['id'],
                    'reason' => $reason,
                ]);
        }

        if ($this->declared !== null) {
            return (string) __('webx-blocks::calls.publish-breaks-declared', ['module' => $this->declared, 'reason' => $reason]);
        }

        return $reason;
    }
}
