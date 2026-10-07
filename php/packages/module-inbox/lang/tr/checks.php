<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Kimseye haber vermeyen formlar',
            'found' => 'Açık bir form, e-postanın ulaşacağı hiçbir alıcı belirtmiyor: hiç alıcı yok, yalnızca o zamandan beri silinmiş ya da kapatılmış yöneticiler var veya adres olmayan adresler.',
            'why' => 'Her gönderim kaydedilir ve ziyaretçiye teşekkür edilir, ama biri gelen kutusunu açana kadar kimsenin haberi olmaz — bir talep günlerce bekleyebilir.',
            'fix' => 'Formu açın, Bildirimler sekmesine gidin ve bir yönetici ya da adres ekleyin. Form yalnızca panelde okunuyorsa bu bulguyu yok sayın.',
        ],
        'notification' => [
            'title' => 'Gönderilemeyen başvuru bildirimleri',
            'found' => 'Başvurularla ilgili mektuplar gönderilemedi ya da uzun süredir kuyrukta bekliyor.',
            'why' => 'Formda belirtilen kişiler panelde zaten bulunan taleplerden habersiz ve site bunu söylemiyor.',
            'fix' => 'Gönderilemeyenler: e-posta ayarlarını düzeltin, kuyruk işçisini yeniden başlatın (php artisan queue:restart) ki ayarları okusun, sonra bildirimi başvurudan yeniden gönderin. Bekleyenler: bir kuyruk işçisi başlatın.',
        ],
        'captcha_keys' => [
            'title' => 'Sitenin anahtarı olmayan bir captcha isteyen formlar',
            'found' => 'Açık bir form reCAPTCHA ya da Turnstile istiyor ve sitenin .env dosyasında site anahtarı, gizli anahtar ya da ikisi birden eksik.',
            'why' => 'Site anahtarı olmadan widget gösterilmez; gizli anahtar olmadan hiçbir yanıt doğrulanamaz. Her iki durumda da form her gönderimi reddeder ve ziyaretçiler size ulaşamaz.',
            'fix' => 'Sitenin .env dosyasına WEBX_INBOX_RECAPTCHA_KEY ve WEBX_INBOX_RECAPTCHA_SECRET’ı (ya da TURNSTILE çiftini) ve anahtar türüne uygun WEBX_INBOX_RECAPTCHA_TYPE’ı ekleyin, sonra yapılandırma önbelleğini temizleyin (php artisan config:clear). Ya da formun Spam koruması sekmesinde captcha’yı kapatın.',
        ],
    ],
];
