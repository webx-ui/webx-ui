<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Ödeme yöntemi',
        'save' => 'Kaydet',
        'delivery_first' => 'Önce teslimatı seçin — ödeme yöntemleri ona bağlıdır',
        'method_dropped' => 'Seçtiğiniz ödeme yöntemi bu teslimat için kullanılamıyor. Başka birini seçin.',
    ],
    'errors' => [
        'choose_method' => 'Bir ödeme yöntemi seçin',
        'method_gone' => 'Ödeme yöntemi artık kullanılamıyor, başka birini seçin',
    ],
    'instructions' => [
        'title' => 'Ödeme için banka bilgileri',
        'recipient' => 'Alıcı',
        'account' => 'Hesap',
        'purpose' => 'Ödeme açıklaması',
        'note' => 'Süre',
    ],
    'status' => [
        'paid' => 'Ödendi',
        'awaiting' => 'Ödeme bekleniyor',
        'pay' => 'Öde',
    ],
    'pay' => [
        'title' => ':number numaralı sipariş için ödeme',
        'description' => 'Sipariş :number',
        'due' => 'Ödenecek: :amount',
        'paid' => ':number numaralı sipariş ödendi',
        'cancelled' => ':number numaralı sipariş iptal edildi',
        'checking' => 'Ödemeyi kontrol ediyoruz. Genellikle bir dakika kadar sürer.',
        'checking_long' => 'Banka ödemeyi henüz onaylamadı. Para çekildiyse sipariş kendiliğinden ödendi olarak işaretlenir ve e-posta göndeririz. Bu sayfayı kapatabilirsiniz.',
        'check_again' => 'Tekrar kontrol et',
        'held' => 'Ödeme kabul edildi. Tutar kartınızda bloke edildi, mağaza tahsil edecek.',
        'received' => 'Ödeme alındı',
        'accepted' => 'Ödeme kabul edildi',
        'failed' => 'Ödeme gerçekleşmedi',
        'unavailable' => 'Ödeme açılamadı. Tekrar deneyin veya başka bir yöntem seçin.',
        'on_receipt' => 'Sipariş teslimatta ödenir',
        'order_link' => 'Siparişe git',
        'redirecting' => 'Ödeme sayfasına yönlendiriliyorsunuz…',
        'go' => 'Ödemeye geç',
        'top_up' => 'Ek ödeme gerekli: :amount',
        'top_up_button' => 'Ek ödeme yap',
        'online_now' => 'Şimdi çevrimiçi öde',
        'wait' => 'Başka bir sekmede zaten ödediyseniz, tekrar ödemeden önce bir dakika bekleyin.',
        'declined' => 'Ödeme gerçekleşmedi: banka kartı reddetti',
        'unfinished' => 'Ödeme tamamlanmadı',
        'pay_again' => 'Yeniden öde',
        'other_method' => 'Başka bir yöntem seç',
    ],
];
