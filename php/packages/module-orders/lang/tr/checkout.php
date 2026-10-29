<?php

declare(strict_types=1);

/*
 * The checkout (spec §33). The words of the page, its steps and the «Thanks» come with their
 * stages; the tab «Checkout» of the settings is here now.
 */
return [
    'settings' => [
        'tab' => 'Ödeme sayfası',
        'fields' => 'Alıcının iletişim bilgileri',
        'fields-help' => 'Ad her zaman sorulur. Bir sipariş için telefon veya e-posta gerekir, bu yüzden en az biri zorunlu kalır.',
        'last-name' => 'Soyadı',
        'phone' => 'Telefon',
        'email' => 'E-posta',
        'recipient' => '«Alıcı başka biri»',
        'recipient-help' => 'Başka birinin teslim alacağı sipariş için iletişim bilgilerinin altında bir onay kutusu.',
        'comment' => 'Yorum',
        'comment-help' => 'Alıcının siparişe yorum bıraktığı alan.',
        'terms-card' => 'Koşullar',
        'terms' => 'Koşulların kabulü',
        'terms-help' => 'Bildirim — düğmenin altında bir satır; onay kutusu — alıcı sipariş vermeden önce işaretler. Kabul, siparişin geçmişine yazılır.',
        'terms-page' => 'Koşullar sayfası',
        'terms-page-help' => 'Kabulün bağlantı verdiği site sayfası veya adres.',
        'privacy-page' => 'Gizlilik politikası',
        'privacy-page-help' => 'Sitede varsa ikinci bir belge: kişisel verilerin nasıl işlendiği.',
        'contact-needed' => 'Telefonu veya e-postayı zorunlu bırakın: bir sipariş için bunlardan biri gerekir.',
        'values' => [
            'required' => 'Zorunlu',
            'optional' => 'İsteğe bağlı',
            'hidden' => 'Gizli',
            'on' => 'Açık',
            'off' => 'Kapalı',
            'none' => 'Yok',
            'notice' => 'Bildirim',
            'checkbox' => 'Onay kutusu',
        ],
    ],
];
