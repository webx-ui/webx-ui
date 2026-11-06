<?php

declare(strict_types=1);

return [
    'template-unclosed' => 'The section {{#:section}} is not closed: end it with {{/:section}}.',
    'template-unexpected' => '{{/:section}} closes a section that was never opened.',
    'template-tag' => 'The tag {{:tag is not closed: end it with }}.',
    'template-name' => '{{:name}} is not a variable name: lowercase letters, digits and _, one dot at most.',
    'template-partial' => 'Partials ({{>name}}) are not available in a message template.',
    'template-delimiters' => 'Changing the delimiters ({{=<% %>=}}) is not available in a message template.',
    'template-pragma' => 'Pragmas and blocks ({{%…}}, {{$…}}) are not available in a message template.',
    'template-cdata' => 'A variable cannot stand inside a literal CDATA block: {{:name}} would be escaped twice. Remove the CDATA around it and use the _cdata variable, such as {{description_cdata}}.',
];
