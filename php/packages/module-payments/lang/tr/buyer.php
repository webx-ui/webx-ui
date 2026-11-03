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
];
