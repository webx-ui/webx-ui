<?php

declare(strict_types=1);

return [
    'required' => 'The field ":field" cannot be empty.',
    'name-long' => 'The name is longer than :max characters.',
    'code-long' => 'The code is :bytes bytes; the limit is :max.',
    'consent' => 'The consent category is one of: :categories.',
    'mask' => 'A path starts from the root, like "/blog/*", without the address of the site or "?": [:mask] is not one.',
    'masks-many' => 'No more than :max paths in a list.',
    'locales' => 'The site is not published in :unknown. It has: :known.',
    'boolean' => 'The field ":field" is yes or no.',
    'list' => 'A list of paths or languages is expected.',
    'place-undeclared' => 'The place ":place" is not declared in config/webx-snippets.php.',
    'place-orphaned' => 'The place ":place" is no longer declared, so the code would be printed nowhere: move it to another place or switch it off.',
    'document-tags' => 'The code has :tags: paste only what the service asks to put inside the page.',
    'unknown' => 'There is no code with the id :ids.',
    'reorder' => 'The order is a place and the list of its codes.',
];
