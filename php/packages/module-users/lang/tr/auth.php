<?php

declare(strict_types=1);

return [
    'sign_in' => [
        'title' => 'Giriş yap',
        'remember' => 'Beni hatırla',
        'submit' => 'Giriş yap',
        'no_account' => 'Henüz hesabınız yok mu?',
        'register' => 'Kayıt olun',
    ],
    'register' => [
        'title' => 'Hesap oluştur',
        'submit' => 'Kayıt ol',
        'password_hint' => 'En az :min karakter.',
        'have_account' => 'Zaten hesabınız var mı?',
        'sign_in' => 'Giriş yapın',
    ],
    'fields' => [
        'email' => 'E-posta',
        'password' => 'Şifre',
        'first_name' => 'Ad',
        'last_name' => 'Soyad',
        'phone' => 'Telefon',
    ],
    'failed' => 'E-posta veya şifre yanlış.',
    'blocked' => 'Bu hesap engellendi. Lütfen mağazayla iletişime geçin.',
    'taken' => 'Bu adres zaten kayıtlı — giriş yapın veya şifrenizi sıfırlayın.',
    'throttled' => 'Çok fazla deneme. Lütfen :seconds sn bekleyin.',
    'forgot' => [
        'link' => 'Şifrenizi mi unuttunuz?',
        'title' => 'Şifreyi kurtar',
        'intro' => 'Hesabınızın e-postasını girin — yeni şifre için bir bağlantı göndereceğiz.',
        'submit' => 'Bağlantıyı gönder',
        'sent' => 'Bu adresle bir hesap varsa, ona bir bağlantı gönderdik.',
        'back' => 'Girişe dön',
    ],
    'reset' => [
        'title' => 'Yeni şifre',
        'password' => 'Yeni şifre',
        'confirmation' => 'Şifreyi tekrarlayın',
        'submit' => 'Şifreyi kaydet',
        'expired' => 'Bağlantının süresi doldu. Aşağıdan yenisini isteyin.',
    ],
    'verify' => [
        'banner' => 'E-postanızı onaylayın: şu adrese bir ileti gönderdik',
        'resend' => 'Yeniden gönder',
        'sent_short' => 'Gönderildi',
        'close' => 'Kapat',
        'sent' => 'İletiyi :email adresine yeniden gönderdik.',
        'already' => 'E-postanız zaten onaylandı.',
        'throttled' => 'İleti zaten gönderildi. :minutes dk sonra yeniden deneyin.',
        'sign_in' => 'Giriş yap',
        'continue' => 'Devam',
        'confirmed' => [
            'title' => 'E-posta onaylandı',
            'text' => 'Teşekkürler — adresiniz onaylandı.',
        ],
        'expired' => [
            'title' => 'Bağlantının süresi doldu',
            'text' => 'Bu bağlantı artık çalışmıyor. Yeni bir ileti almak için giriş yapın.',
        ],
        'unverified' => [
            'title' => 'E-postanızı onaylayın',
            'text' => 'Bu sayfa e-postanızı onayladıktan sonra açılır. :email adresine bir ileti gönderdik.',
        ],
    ],
    'email' => [
        'new' => 'Yeni e-posta',
        'password' => 'Mevcut şifre',
        'wrong_password' => 'Şifre yanlış.',
        'same' => 'Zaten bu adresle giriş yapıyorsunuz.',
        'taken' => 'Bu adres başka bir hesaba ait.',
        'sent' => ':email adresine bir bağlantı gönderdik. Adres, bağlantıyı açtığınızda değişir.',
        'changed' => [
            'title' => 'Adres değişti',
            'text' => 'Artık :email ile giriş yapıyorsunuz.',
        ],
    ],
];
