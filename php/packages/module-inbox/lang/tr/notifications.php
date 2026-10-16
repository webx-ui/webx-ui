<?php

declare(strict_types=1);

// The event `inbox.submission` of webx-ui/module-notifications: its name and the values its templates get.
return [
    'event' => 'Bir formdan gelen gönderim',
    'vars' => [
        'form' => 'Formun adı',
        'form_slug' => 'Formun slug’ı',
        'submission_id' => 'Gönderimin numarası',
        'submission_url' => 'Paneldeki gönderim',
        'created_at' => 'Ne zaman geldiği',
        'fields' => 'Tüm yanıtlar, her biri bir satırda: “Etiket: değer”',
        'answers' => 'Liste olarak yanıtlar',
        'answer_name' => 'Alanın makine adı',
        'answer_label' => 'Alanın etiketi',
        'answer_value' => 'Yanıt',
        'field' => 'Alanının makine adına göre tek bir yanıt',
        'files' => 'Ekli dosyaların adları',
        'page' => 'Formun gönderildiği sayfa',
        'locale' => 'Gönderimin dili',
        'utm' => 'UTM etiketleri',
        'reply_to' => 'Formun e-posta alanından ziyaretçinin e-postası',
    ],
];
