<?php

declare(strict_types=1);

/*
 * The page that hands a person the address and walks them through their client. It is read
 * once, by somebody who has never done this before, and often out loud over a call.
 */

return [
    'lead' => 'Agent AI — Claude, ChatGPT, Cursor — potrafi pracować w tym panelu w Twoim imieniu. Podaj mu adres poniżej: poprosi Cię o zalogowanie, a potem zapyta, co mu wolno.',
    'title' => 'Podłączenie agenta',
    'address' => 'Adres tego panelu dla agentów',
    'copy' => 'Skopiuj adres',
    'copied' => 'Skopiowano',
    'copy-failed' => 'Schowek jest niedostępny. Zaznacz adres i skopiuj ręcznie.',
    'copy-line' => 'Skopiuj',
    'how' => 'Jak dodać',
    'claude-title' => 'Claude',
    'claude-1' => 'Otwórz ustawienia i przejdź do „Connectors”.',
    'claude-2' => 'Naciśnij „Add custom connector”.',
    'claude-3' => 'Wklej adres i naciśnij „Add”.',
    'chatgpt-title' => 'ChatGPT',
    'chatgpt-1' => 'Otwórz ustawienia i przejdź do „Connectors”.',
    'chatgpt-2' => 'Naciśnij „Add” i wybierz własny serwer MCP.',
    'chatgpt-3' => 'Wklej adres, zostaw logowanie przez OAuth i potwierdź.',
    'claude-code-title' => 'Claude Code',
    'claude-code-hint' => 'Jedna linia w terminalu, z dowolnego projektu:',
    'codex-title' => 'Codex',
    'codex-hint' => 'Te linie w ~/.codex/config.toml:',
    'one-click' => 'Jednym kliknięciem',
    'one-click-hint' => 'Te dwa przyjmują całość z linku: edytor się otworzy, zapyta raz — i serwer jest na miejscu.',
    'install-in' => 'Dodaj do :client',
    'cursor-title' => 'Cursor',
    'vscode-title' => 'VS Code',
    'next' => 'Co będzie dalej',
    'next-1' => 'Klient otworzy tę stronę w przeglądarce i poprosi o zalogowanie do panelu zwykłym hasłem. Hasła nigdy nie zobaczy.',
    'next-2' => 'Panel pokaże, kto prosi o dostęp i dokąd trafi odpowiedź. Jest tam też przełącznik „tylko odczyt” — bezpieczniej zacząć z nim.',
    'next-3' => 'Od tej chwili agent działa w Twoim imieniu: może dokładnie to, co Ty, i każde jego wywołanie jest zapisywane.',
    'mine' => 'Twoje połączenia',

];
