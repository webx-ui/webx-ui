<?php

declare(strict_types=1);

namespace WebxUi\Compositions\View\Components;

use Illuminate\View\Component;
use WebxUi\Compositions\Compositions;

/**
 * `<x-webx-composition::place id="body" only="added" />` (COMPOSITIONS §6.4): the children of one
 * container of the final tree, in a view a site wrote by hand — its own markup, and the pieces
 * modules add arriving with their releases. `only="all"` prints every child but the caller's
 * slots; `only="added"` only what was added above the base, which the hand-written file cannot know.
 */
final class Place extends Component
{
    public function __construct(
        private readonly Compositions $compositions,
        public string $id,
        public string $composition = 'layout',
        public ?string $handle = null,
        public string $only = 'all',
    ) {}

    public function render(): string
    {
        return $this->compositions->place($this->composition, $this->handle, $this->id, $this->only === 'added');
    }
}
