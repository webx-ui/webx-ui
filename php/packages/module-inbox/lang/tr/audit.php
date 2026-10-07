<?php

declare(strict_types=1);

return [
    'no-recipients' => ':form (:slug) formu kimseye haber vermiyor',
    'notify-failed' => 'Son :days günde gönderilemeyen bildirimler: :count',
    'notify-queued' => ':minutes dakikadan uzun süredir kuyrukta bekleyen bildirimler: :count',
    'captcha-keys' => ':form (:slug) formu :provider istiyor ve sitenin .env dosyasında :missing eksik',
    'captcha-unused' => ':form (:slug) formunda captcha yok ve sitenin :providers için anahtarı var',
    'spam-without-captcha' => ':form (:slug) formunda captcha yok ve spam alıyor. :days günde spam olarak işaretlenen: :spam, reddedilen: :refused',
];
