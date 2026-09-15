<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use WebxUi\Blocks\Exceptions\BlockNotPublishable;
use WebxUi\Blocks\Exceptions\BlocksException;

/**
 * A publish check that failed, with where: the sample, or a page named by model and id. The
 * controller turns it into a 422 the editor understands — the message under the template with
 * the line, and the page it broke on when it was a page.
 */
final class PublishFailed extends BlocksException
{
    /**
     * @param  array{model: string, id: int|string, title: string|null}|null  $entity
     */
    private function __construct(
        public readonly BlockNotPublishable $failure,
        public readonly ?array $entity,
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
}
