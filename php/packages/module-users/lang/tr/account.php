<?php

declare(strict_types=1);

return [
    'menu' => [
        'label' => 'Hesabım',
        'profile' => 'Profil',
        'security' => 'Giriş ve güvenlik',
        'sign_out' => 'Çıkış yap',
    ],
    'profile' => [
        'title' => 'Profil',
        'personal' => 'Kişisel bilgiler',
        'save' => 'Kaydet',
        'saved' => 'Kaydedildi.',
    ],
    'email' => [
        'title' => 'E-posta',
        'confirmed' => 'onaylandı',
        'unconfirmed' => 'onaylanmadı',
        'pending' => 'Onay bekleniyor: :email — e-postadaki bağlantıyı izleyin.',
        'submit' => 'E-postayı değiştir',
    ],
    'security' => [
        'title' => 'Giriş ve güvenlik',
    ],
    'password' => [
        'title' => 'Şifre',
        'current' => 'Mevcut şifre',
        'new' => 'Yeni şifre',
        'confirmation' => 'Yeni şifreyi tekrarlayın',
        'submit' => 'Şifreyi değiştir',
        'changed' => 'Şifre değiştirildi. Diğer cihazlarda oturum kapatıldı.',
        'none' => 'Hesabınızın henüz şifresi yok. Şifre belirlemeniz için bir bağlantı göndereceğiz.',
        'set' => 'Şifre belirle',
        'link_sent' => 'Bağlantıyı :email adresine gönderdik.',
    ],
    'sessions' => [
        'title' => 'Her yerden çıkış yap',
        'text' => 'Diğer tüm cihaz ve tarayıcılardaki oturumları sonlandırır. Burada oturumunuz açık kalır.',
        'submit' => 'Her yerden çıkış yap',
        'done' => 'Diğer tüm cihazlarda oturumunuz kapatıldı.',
    ],
    'delete' => [
        'title' => 'Hesabı sil',
        'text' => 'Hesap ve içindeki her şey kalıcı olarak silinir. Geri yüklenemez.',
        'password' => 'Mevcut şifre',
        'email' => 'E-postanız',
        'email_hint' => 'Onaylamak için hesabın adresini yazın.',
        'wrong_email' => 'Bu, hesabın adresi değil.',
        'submit' => 'Kalıcı olarak sil',
    ],
    'social' => [
        'title' => 'Sosyal ağlar',
        'link' => 'Bağla',
        'unlink' => 'Bağlantıyı kaldır',
        'linked_short' => 'Bağlı',
        'since' => ':date tarihinden beri',
        'linked' => ':provider ile giriş bağlandı.',
        'elsewhere' => 'Bu :provider hesabı zaten başka bir profile bağlı.',
        'other' => 'Başka bir :provider hesabı zaten bağlı. Önce onun bağlantısını kaldırın.',
        'last' => 'Son giriş yönteminin bağlantısını kaldırmak için bir şifre belirleyin.',
        'not_linked' => 'Bu ağ bağlı değil.',
    ],
];
