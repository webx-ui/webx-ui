<?php

declare(strict_types=1);

// What the server says about types calling each other by tag (the components spec): kinds, the
// graph, the checks before publishing, the declared places. Server-only — the panel shows these
// as they come in the response, so there is no copy of this group in the npm package.
return [
    'kind' => 'A type is either a block or a component.',
    'kind-in-use' => 'The block stands on pages and cannot become a component. Pages: :count.',
    'unknown-call' => '":type" is not a block type: the call prints nothing on the site.',
    'dynamic-call' => 'The called type is not written out: publishing it will not check this template.',
    'delete-used-by' => 'Other types call this one, and their templates would print a gap. Remove the calls first.',
    'delete-used-by-one' => 'Called by ":title" (:slug)',
    'publish-cycle' => 'The types call each other in a circle: :path.',
    'publish-breaks-parent' => 'Breaks ":parent" on its sample: :reason',
    'publish-breaks-parent-on' => 'Breaks ":parent" on ":entity": :reason',
    'publish-breaks-declared' => 'Breaks the place the :module module calls it from: :reason',
    'customise-exists' => 'A type with this identifier already exists.',
    'customised-from' => 'From :view',
];
