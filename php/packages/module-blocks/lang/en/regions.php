<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'Header',
    'footer' => 'Footer',
    'usage' => 'Region “:title”',
    'preview-failed' => 'A block of this region fails. On the site the whole region prints the markup from code instead.',
    'not-in-registry' => ':path is not a page of the site’s address registry, so the region is shown on an empty page of the layout.',
    'too-many' => 'The region holds at most :max blocks.',
    'not-allowed' => 'The block “:type” cannot be placed in this region.',
    'refused' => 'The region does not take these blocks.',
    'conflict' => 'The region was changed since you opened it. Reload it to see the changes.',
    'failed-block' => 'Block “:type” (:key) fails: :reason',
    'not-published' => 'Not published: a block of the draft fails to render.',
    'nothing-to-publish' => 'The region has never been saved: there is nothing to publish.',
    'no-version' => 'The region has no version :number.',
    'no-fallback' => 'The layout has not named a view for this region yet, or the view is gone.',
    'adopt-taken' => 'A block type “:slug” already exists.',
    'adopt-failed' => 'The markup could not become a block type.',
    'adopt-forbidden' => 'Moving the markup into a block type needs the right to edit block types.',
];
