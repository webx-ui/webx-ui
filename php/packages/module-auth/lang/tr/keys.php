<?php

declare(strict_types=1);

/*
 * Keys for programs: issued under Administrators → Keys, shown once, revoked there. The words
 * are about a key a program holds, not about tokens and scopes underneath — except the scopes
 * themselves, which are kept as issued because an agent's refusal quotes them.
 */

return [
    'title' => 'Anahtarlar',
    'issue' => 'Anahtar oluştur',
    'issue-short' => 'Oluştur',
    'lead' => 'Anahtar, bir programın — kuyruk bekçisi, gece içe aktarımı — bu panelde tarayıcı olmadan çalışmasını sağlar. Seçtiğiniz yönetici olarak hareket eder ve asla onun yapabileceğinden fazlasını yapamaz. Anahtar bir programdır, kişi değil: bir modülün kişiye bıraktığı şeyi, örneğin elle kontrol edilen bir aşamayı, yapamaz.',
    'name' => 'Ad',
    'name-hint' => 'Programın ne olduğu, liste bunu söylesin diye: “kuyruk bekçisi”, “gece içe aktarımı”.',
    'who' => 'Kimin adına',
    'who-hint' => 'Anahtarın değiştirdiği her şey bu yönetici adına ve onun yetkileri içinde yapılır.',
    'allowed' => 'Neye izin var',
    'everything' => 'Yöneticinin yapabildiği her şey',
    'chosen' => 'Yalnızca aşağıda seçilen kapsamlar',
    'scopes' => 'Kapsamlar',
    'scopes-hint' => 'Sonradan kurulan bir modüle, yeni bir anahtar onu adlandırana kadar erişilemez.',
    'scopes-required' => 'En az bir kapsam seçin.',
    'lifetime' => 'Geçerlilik',
    'lifetime-hint' => 'Anahtar uzatılamaz: bu bitmeden yenisini oluşturun, sonra eskisini iptal edin.',
    'days-30' => '30 gün',
    'days-90' => '90 gün',
    'days-365' => '1 yıl',
    'days-1095' => '3 yıl',
    'days-3650' => '10 yıl',
    'issued-title' => 'Anahtarı şimdi kopyalayın',
    'issued-text' => 'Anahtar yalnızca bu kez gösterilir. Bir daha kimse göremez — ne burada ne sunucuda. Kaybolursa iptal edip yenisini oluşturun.',
    'secret' => 'Anahtar',
    'copy' => 'Kopyala',
    'copied' => 'Kopyalandı',
    'copy-failed' => 'Panoya erişilemedi. Anahtarı seçip elle kopyalayın.',
    'address' => 'Sunucu adresi',
    'header-hint' => 'Program anahtarı bir başlıkta gönderir: Authorization: Bearer <anahtar>.',
    'done' => 'Tamam',
    'cancel' => 'İptal',
    'expires' => 'Bitiş',
    'until' => 'şu tarihe kadar',
    'last-used' => 'Son kullanım',
    'never-used' => 'Hiç',
    'issued-by' => ':name oluşturdu',
    'issued-console' => 'Terminalde oluşturuldu',
    'full' => ':name kişisinin yapabildiği her şey',
    'deleted' => 'Yönetici #:id',
    'revoke' => 'İptal et',
    'revoked' => 'İptal edildi',
    'expired' => 'Süresi doldu',
    'confirm-title' => '“:name” iptal edilsin mi?',
    'confirm-text' => 'Onu kullanan program bir sonraki çağrısında reddedilir. İptal edilen anahtar yeniden açılamaz.',
    'confirm' => 'İptal et',
    'revoked-done' => '“:name” iptal edildi',
    'empty' => 'Henüz anahtar oluşturulmadı',
    'lead-mine' => 'Anahtar, bir programın — kuyruk bekçisi, gece içe aktarımı — bu panelde tarayıcısız olarak sizin adınıza çalışmasını sağlar. Asla sizden fazlasını yapamaz ve yalnızca seçtiğiniz kapsamlarda çalışır.',
    'empty-mine' => 'Henüz anahtarınız yok',

];
