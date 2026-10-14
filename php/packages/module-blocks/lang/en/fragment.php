<?php

declare(strict_types=1);

return [
    'choose' => 'Choose a shared block',
    'none' => 'The site has no shared blocks yet. A developer declares one under regions in config/webx-blocks.php, with fragment set to true.',
    'missing' => '“:name” is not a shared block of the site any more: nothing is shown here.',
    'status-live' => 'On the site',
    'status-edits' => 'On the site, edits waiting',
    'status-fallback' => 'Not published: the site shows its fallback',
    'status-empty' => 'Empty',
    'explain' => 'This block shows a shared block of the site. Editing it changes it on every page where it stands.',
    'edit' => 'Edit for all pages',
    'detach' => 'Make it this page’s own',
    'detach-title' => 'Make “:title” this page’s own?',
    'detach-text' => 'Its blocks are copied into this page in its place, as they are on the site. They stop following the shared block: its edits will not reach this page any more.',
    'detached' => 'The blocks of “:title” are this page’s own now.',
    'unpublished' => 'The shared block is not published yet: there is nothing to copy.',
    'group-layout' => 'Header and footer',
    'group-shared' => 'Shared blocks',
    'group-shared-help' => 'Edited once here and shown wherever a page or a template of the site places them.',
    'count' => 'Pages: :count',
    'used-on' => 'Where it stands',
    'used-nowhere' => 'No page places it yet.',
    'templates' => 'Templates of the site that print it with a tag are not listed.',
    'draft-only' => 'In a draft',
    'show-here' => 'Preview on this page',
    'publish-text' => 'Every page that places it shows these blocks instead of the code’s view.',
];
