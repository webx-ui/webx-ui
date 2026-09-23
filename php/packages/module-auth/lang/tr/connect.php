<?php

declare(strict_types=1);

/*
 * The page that hands a person the address and walks them through their client. It is read
 * once, by somebody who has never done this before, and often out loud over a call.
 */

return [
    'lead' => 'Bir yapay zekâ aracısı — Claude, ChatGPT, Cursor — bu panelde sizin adınıza çalışabilir. Ona aşağıdaki adresi verin: sizden oturum açmanızı, sonra da neye izin verdiğinizi soracak.',
    'title' => 'Aracı bağla',
    'address' => 'Bu panelin aracılar için adresi',
    'copy' => 'Adresi kopyala',
    'copied' => 'Kopyalandı',
    'copy-failed' => 'Panoya erişilemedi. Adresi seçip elle kopyalayın.',
    'copy-line' => 'Kopyala',
    'how' => 'Nasıl eklenir',
    'claude-title' => 'Claude',
    'claude-1' => 'Ayarları açıp „Connectors” bölümüne gidin.',
    'claude-2' => '„Add custom connector” düğmesine basın.',
    'claude-3' => 'Adresi yapıştırıp „Add” düğmesine basın.',
    'chatgpt-title' => 'ChatGPT',
    'chatgpt-1' => 'Ayarları açıp „Connectors” bölümüne gidin.',
    'chatgpt-2' => '„Add” düğmesine basıp kendi MCP sunucunuzu seçin.',
    'chatgpt-3' => 'Adresi yapıştırın, kimlik doğrulamayı OAuth bırakın ve onaylayın.',
    'claude-code-title' => 'Claude Code',
    'claude-code-hint' => 'Terminalde tek satır, herhangi bir projeden:',
    'codex-title' => 'Codex',
    'codex-hint' => '~/.codex/config.toml dosyasına şu satırlar:',
    'one-click' => 'Tek tıkla',
    'one-click-hint' => 'Bu ikisi her şeyi bir bağlantıdan alır: düzenleyici açılır, bir kez sorar ve sunucu yerindedir.',
    'install-in' => ':client uygulamasına ekle',
    'cursor-title' => 'Cursor',
    'vscode-title' => 'VS Code',
    'next' => 'Sonra ne olacak',
    'next-1' => 'İstemci bu siteyi tarayıcıda açar ve panele her zamanki parolanızla girmenizi ister. Parolayı asla görmez.',
    'next-2' => 'Panel kimin istediğini ve yanıtın nereye gideceğini gösterir. O ekranda „yalnızca okuma” anahtarı vardır; işe onunla başlamak güvenli olanıdır.',
    'next-3' => 'Bundan sonra aracı sizin adınıza davranır: sizin yapabildiğinizi yapar, fazlasını değil, ve her çağrısı kaydedilir.',
    'mine' => 'Bağlantılarınız',

];
