<?php

declare(strict_types=1);

return [
    'name' => 'Name',
    'name-help' => 'For administrators only.',
    'provider' => 'Provider',
    'provider-help' => 'Chosen once: a channel of another kind is a new channel.',
    'event' => 'For the event',
    'event-help' => 'With an event, the editor suggests and checks its variables, and only this event offers the channel. Without one, the channel serves any event.',
    'any-event' => 'Any event',
    'enabled' => 'Enabled',
    'enabled-help' => 'A switched-off channel sends nothing but tests.',
    'connection' => 'Connection',
    'message' => 'Message',
    'subject' => 'Subject',
    'subject-help' => 'One line; variables like {{form}} are filled in.',
    'template' => 'Template',
    'template-help' => 'Variables in double braces: {{form}}. One language is enough; a message in a language the template lacks uses the site\'s main language.',
    'history' => 'History',
    'token-help' => 'From @BotFather. Saved encrypted and never shown again: type a new one to replace it.',
    'chats' => 'Chats',
    'chats-help' => 'Chat ids, or "Find chats" after writing to the bot. A recipient from the event is the address the event brings.',
    'mode-help' => 'The site\'s mail is the one set for the whole site.',
    'from-help' => 'Empty — the site\'s sender.',
    'emails' => 'Recipients',
    'emails-help' => 'Administrators, addresses, or the address the event brings — the visitor who filled the form.',
    'credentials' => 'Secrets',
    'options' => 'Settings',
    'recipients' => 'Recipients',
];
