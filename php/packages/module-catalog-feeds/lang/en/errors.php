<?php

declare(strict_types=1);

return [
    'currency' => 'A feed is in the site’s currency: :currency.',
    'currency-unset' => 'The site has no currency: set WEBX_CATALOG_CURRENCY.',
    'filter-facet' => 'The filter of the feed names [:facet], which the site does not have.',
    'source-property' => 'The feed takes its MPN from the property [:property], which the site does not have.',
    'no-item' => 'The feed has no template of a product.',
    'template' => 'The template cannot be read: :reason',
    'too-large' => 'The file is over the limit of a feed’s size.',
    'stale' => 'The build stopped moving and was closed.',
    'file-xml' => 'The file is not valid XML, line :line: :reason',
    'file-json' => 'The head and the foot do not make valid JSON: :reason',
    'file-json-item' => 'Product :product does not make valid JSON: :reason',
    'file-csv' => 'Line :line of the file has :found fields instead of :expected.',
    'sample-json' => 'The sample is not valid JSON, line :line: :reason',
    'sample-place' => 'Part “:part”, line :line: :fragment',
    'unknown-variable' => '{{:name}} is not known in the part “:part”.',
    'no-products' => 'No product suits the feed yet: only the header and the footer were checked.',
];
