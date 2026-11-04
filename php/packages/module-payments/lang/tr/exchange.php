<?php

declare(strict_types=1);

// The journal of the exchange with gateways (spec §20): directions, kinds, signatures, outcomes.
return [
    'title' => 'Ödeme geçidiyle alışveriş',
    'directions' => [
        'out' => 'Geçide',
        'in' => 'Geçitten',
        'browser' => 'Alıcının tarayıcısı üzerinden',
        'internal' => 'Sitenin kararı',
    ],
    'kinds' => [
        'start' => 'Ödeme açıldı',
        'status' => 'Durum sorgusu',
        'cancel' => 'İptal',
        'complete' => 'Onay tamamlandı',
        'capture' => 'Tahsilat isteği',
        'void' => 'Serbest bırakma isteği',
        'connection' => 'Bağlantı testi',
        'key' => 'İmza anahtarı',
        'other' => 'Diğer istek',
        'notify' => 'Bildirim',
        'handoff' => 'Geçide gönderildi',
        'return' => 'Geçitten dönüş',
        'expire' => 'Süresi doldu',
        'manual' => 'Elle işaretlendi',
        'captured' => 'Tahsil edildi',
        'released' => 'Provizyon serbest bırakıldı',
        'capture_refused' => 'Tahsilat reddedildi',
        'release_refused' => 'Serbest bırakma reddedildi',
    ],
    'signature' => [
        'valid' => 'İmza geçerli',
        'invalid' => 'İmza geçersiz',
        'untrusted' => 'İmzasız — durum geçide soruldu',
        'none' => 'Denetlenmedi',
    ],
    'outcomes' => [
        'applied' => 'Uygulandı',
        'same' => 'Yeni bir şey yok',
        'ignored' => 'Yok sayıldı',
        'duplicate' => 'Tekrar',
        'rejected' => 'Reddedildi',
        'unmatched' => 'Böyle bir ödeme yok',
        'error' => 'Hata',
    ],
    'truncated' => 'Sınırda kesildi',
    'download' => 'İndir',
    'size' => 'Boyut',
    'masked' => 'Gizli bilgiler saklandı',
];
