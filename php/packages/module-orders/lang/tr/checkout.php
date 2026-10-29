<?php

declare(strict_types=1);

/*
 * The checkout (spec §33): the page, its steps, the column of the totals, and the tab «Checkout»
 * of the settings.
 */
return [
    'page' => [
        'title' => 'Sipariş',
        'empty' => 'Sepetiniz boş.',
        'shopping' => 'Mağazaya dön',
        'open-cart' => 'Sepeti aç',
    ],
    'steps' => [
        'contacts' => 'İletişim bilgileri',
        'comment' => 'Yorum',
        'edit' => 'Değiştir',
        'save' => 'Kaydet',
        'signed-in' => ':name olarak giriş yaptınız',
    ],
    'fields' => [
        'first-name' => 'Ad',
        'last-name' => 'Soyad',
        'phone' => 'Telefon',
        'phone-hint' => 'Örneğin +800 1234 5678',
        'email' => 'E-posta',
        'recipient' => 'Alıcı başka biri',
        'recipient-legend' => 'Alıcı',
        'recipient-first-name' => 'Alıcının adı',
        'recipient-last-name' => 'Alıcının soyadı',
        'recipient-phone' => 'Alıcının telefonu',
        'comment' => 'Sipariş notu',
    ],
    'summary' => [
        'title' => 'Siparişiniz',
        'units' => ':count ürün, toplam|:count ürün, toplam',
        'discount' => 'İndirim',
        'payable' => 'Ödenecek tutar',
        'lines' => 'Sipariş içeriği',
        'price-down' => 'artık daha ucuz',
        'price-up' => 'artık daha pahalı',
        'place' => 'Siparişi onayla',
    ],
    'terms' => [
        'agree' => 'Koşulları kabul ediyorum',
        'notice' => 'Düğmeye basarak koşulları kabul etmiş olursunuz',
        'terms' => 'Satış koşulları',
        'privacy' => 'Gizlilik politikası',
    ],
    'errors' => [
        'required' => '«:field» alanını doldurun.',
        'phone-or-email' => 'Bir telefon ya da e-posta girin.',
        'phone' => 'Telefon + ve rakamlardan oluşur, örneğin +800 1234 5678.',
        'email' => 'E-postayı kontrol edin.',
        'terms' => 'Sipariş vermek için koşulları kabul edin.',
        'payable-changed' => 'Tutar değişti: önce :was idi, şimdi :now. Siparişi kontrol edip yeniden onaylayın.',
        'not-orderable' => 'Sepetteki bazı ürünler sipariş edilemez. Sepeti açın.',
    ],
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
