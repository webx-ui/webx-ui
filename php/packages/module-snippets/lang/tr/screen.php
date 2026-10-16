<?php

declare(strict_types=1);

return [
    'name' => 'Ad',
    'name-help' => 'Yalnızca yöneticiler için, sitede hiç görünmez.',
    'place' => 'Yer',
    'place-help' => 'Kodun sayfada nereye yazıldığı. Kendi yerleriniz config/webx-snippets.php içinde tanımlanır.',
    'code' => 'Kod',
    'code-help' => 'Hizmetin verdiğini aynen yapıştırın; <html>, <head> ve <body> olmadan. Onay bekleyen kodda document.write çalışmaz.',
    'settings' => 'Ayarlar',
    'enabled' => 'Etkin',
    'enabled-help' => 'Etkin kod kaydedilir kaydedilmez sitededir.',
    'consent' => 'Çerez onayı',
    'consent-help' => '“Gerekli” dışındaki her şey yalnızca ziyaretçi o kategoriyi onayladıktan sonra yazılır.',
    'on-stage' => 'Dev ve stage’de de',
    'on-stage-help' => 'Kapalı: kod yalnızca canlıda yazılır, sitenin bir kopyası ziyaret saymaz.',
    'coverage' => 'Nerede yazılır',
    'only-paths' => 'Yalnızca şurada',
    'only-paths-help' => 'Dil olmadan yollar, * herhangi bir şey: /, /blog/*. Boş, sitenin tamamı demektir.',
    'except-paths' => 'Şunlar hariç',
    'except-paths-help' => 'Kodun asla yazılmadığı yollar, örneğin /blog/drafts/*.',
    'locales' => 'Diller',
    'locales-help' => 'Hiçbiri işaretli değilse tüm diller.',
    'history' => 'Geçmiş',
    'position' => 'Sıra',
];
