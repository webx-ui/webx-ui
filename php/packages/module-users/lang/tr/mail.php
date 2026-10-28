<?php

declare(strict_types=1);

return [
    'hello' => 'Merhaba, :name!',
    'ignore' => 'Bunu siz istemediyseniz, bu iletiyi dikkate almayın.',
    'verify' => [
        'subject' => ':site üzerinde e-postanızı onaylayın',
        'text' => 'Bunun :site üzerindeki adresiniz olduğunu onaylayın.',
        'button' => 'E-postayı onayla',
        'expires' => 'Bağlantı :hours saat geçerlidir.',
    ],
    'reset' => [
        'subject' => ':site üzerinde yeni şifre',
        'text' => 'Birisi :site üzerindeki hesabınızın şifresini kurtarmak istedi.',
        'button' => 'Yeni şifre belirle',
        'expires' => 'Bağlantı :minutes dakika ve yalnızca bir kez geçerlidir.',
    ],
    'password-changed' => [
        'subject' => ':site şifreniz değiştirildi',
        'text' => ':site üzerindeki hesabınızın şifresi az önce değiştirildi.',
        'not-you' => 'Bu siz değilseniz, şifreyi hemen kurtarın.',
        'button' => 'Şifreyi kurtar',
    ],
    'email-change' => [
        'subject' => ':site üzerinde yeni adresi onaylayın',
        'text' => 'Bundan sonra :site sitesine :email ile girmek için bağlantıyı açın.',
        'button' => 'Adresi onayla',
        'expires' => 'Bağlantı :minutes dakika geçerlidir.',
    ],
    'email-changed' => [
        'subject' => ':site giriş adresiniz değiştirildi',
        'text' => ':site üzerindeki hesabınız artık :email ile giriş yapıyor.',
        'not-you' => 'Bu siz değilseniz, şifreyi kurtarın ve bizimle iletişime geçin.',
        'button' => 'Şifreyi kurtar',
    ],
    'account-deleted' => [
        'subject' => ':site hesabınız silindi',
        'text' => ':site hesabınız verileriyle birlikte silindi.',
        'not-you' => 'Bunu siz yapmadıysanız hemen bizimle iletişime geçin.',
    ],
    'provider-linked' => [
        'subject' => ':site üzerinde :provider ile giriş bağlandı',
        'text' => ':site hesabınıza artık :provider ile giriş yapabilirsiniz.',
        'not-you' => 'Bu siz değilseniz, hesabınızda bağlantıyı kaldırın ve şifreyi sıfırlayın.',
        'button' => 'Giriş ve güvenlik',
    ],
];
