<?php

declare(strict_types=1);

return [
    'refused' => 'Form gönderilemedi. Lütfen birazdan tekrar deneyin.',
    'enclosure' => 'Bu forma eklenen şey onunla gönderilemez. Sayfayı yenileyip tekrar deneyin.',
    'captcha' => 'Lütfen robot olmadığınızı doğrulayın ve formu yeniden gönderin.',
    'captcha-script' => 'Bu form robot olmadığınızı JavaScript ile denetler. Lütfen JavaScript’i açın ve formu yeniden gönderin.',
    'captcha-unavailable' => 'Robot olmadığınızı doğrulayamadık. Lütfen sayfayı yenileyin ya da başka bir tarayıcı deneyin.',
    'too-many' => 'Bu adresten çok fazla gönderim yapıldı. Lütfen bir dakika sonra tekrar deneyin.',
    'form-has-submissions' => '“:form” formunda gönderimler var; kapatılabilir ama silinemez.',
    'status-in-use' => '“:status” durumunda hâlâ gönderimler var; bu durum silinemez.',
    'no-statuses' => 'Yeni bir gönderime verilecek durum yok. Geçişleri çalıştırın.',
    'file-missing' => 'Bu dosya artık burada değil.',

    'slug-empty' => 'Adreste harf ya da rakam olmalı, örneğin “contact-us”.',
    'field-name-empty' => 'Adda harf ya da rakam olmalı, örneğin “phone”.',
    'status-key-empty' => 'Anahtarda harf ya da rakam olmalı, örneğin “in-progress”.',
    'recipient-shape' => ':entry alıcısı ne bir yönetici ne de bir e-posta adresi.',
    'recipient-unknown' => ':entry alıcısı var olmayan bir yöneticiyi gösteriyor.',
    'choices-required' => 'Bir liste alanının değeri olan en az bir seçeneği olmalı.',
    'email-field' => 'Yanıt alanı bu formun bir e-posta alanı olmalı.',
    'no-such-answer' => 'Bu gönderimde bu adla bir yanıt yok.',
    'not-correctable' => 'Bir dosya ya da onay düzeltilemez.',
    'nobody-to-notify' => '“:form” formu kime yazılacağını belirtmiyor.',
    'condition-unknown-field' => '“:field” alanının “:key” koşulu “:name” alanını anıyor; bu formda böyle bir alan yok (ya da bu değişiklik onu kaldırıyor).',
    'condition-not-choice' => '“:field” alanının “:key” koşulu “:name” alanını anıyor, ancak bu bir seçim alanı değil: koşul yalnızca yukarıdaki bir liste, seçenek düğmeleri, onay kutuları ya da onay olabilir.',
    'condition-not-before' => '“:field” alanının “:key” koşulu “:name” alanını anıyor, ancak o yukarıda değil. Koşul yalnızca yukarıdaki bir alanı anar.',
    'condition-unknown-value' => '“:field” alanının “:key” koşulu “:name” alanının “:value” değerini bekliyor, ancak bu alan böyle bir seçenek sunmuyor.',
    'field-has-dependents' => '“:field” alanı :fields alanlarının koşullarında geçiyor. Önce bu koşulları değiştirin.',
    'step-empty' => ':field adımından sonra hiç alan yok. Altına bir alan koyun ya da adımı kaldırın.',
    'step-too-many' => 'Bir formda en fazla :max adım olur.',
    'step-unknown' => 'Sayfa açıldığından beri form değişti. Sayfayı yeniden yükleyin.',
    'datetime-range' => 'Günde hiçbir saate yer yok: «Başlangıç» ve «Bitiş» SS:DD biçiminde olmalı ve «Başlangıç», «Bitiş»ten en az bir adım önce gelmeli.',
    'datetime-step' => 'Saatlerin adımı 15, 20, 30, 60, 90 veya 120 dakikadır.',
    'datetime-weekdays' => 'Haftanın her günü bir kez, 1 (pazartesi) ile 7 (pazar) arasında.',
];
