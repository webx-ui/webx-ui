<?php

declare(strict_types=1);

// The consent screen: what a person reads when their agent asks to be let into the panel.
// The second paragraph of the warning stays whatever the mode — it is what a grant on file
// says the person was shown.
return [
    'page-title' => ':client asks for access',
    'asks' => 'asks for access to the panel of :site',
    'signed-in-as' => 'You are signed in as :name',
    'switch' => 'sign in as somebody else',
    'can-do' => 'The agent will be able to do in the panel everything you can:',
    'everything' => 'Everything: you are a super administrator, so your agent is one too.',
    'nothing' => 'Nothing yet: your roles grant nothing an agent could use.',
    'view' => ':module — view',
    'edit' => ':module — view and edit',
    'warning' => 'It acts in your name: its changes will be signed by you, and not every one of them can be undone. Allow it only if you started this connection yourself.',
    'liability' => 'You are responsible for what the agent does in your name. It can change or delete the content of the site and break the site. The developer of the site is not responsible for that.',
    'read-only' => 'Read only — let it look, but change nothing',
    'allow' => 'Allow',
    'cancel' => 'Cancel',
    'disconnect' => 'You can disconnect it in the panel: Administrators → Connections.',
    'returns-to' => 'The answer goes to :host',
];
