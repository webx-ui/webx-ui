<?php

declare(strict_types=1);

/*
 * Where the site's letters go, as the panel, the doctor and the agent say it
 * (`WebxUi\Admin\Mail\MailRoutes`).
 */

return [
    'trap-transport' => 'E-postalar “:transport” göndericisine gidiyor: orada kaydediliyor ve kimse almıyor.',
    'trap-host' => 'E-postalar bir test posta kutusu olan :host adresine gidiyor: onun dışında kimse almıyor.',
    'stale-config' => '.env içindeki ayarlar yapılandırma önbelleğe alındıktan sonra değiştirildi ve henüz geçerli değil. Önbelleği yeniden oluşturun: php artisan webx:boot.',
    'queue-delay' => 'E-postalar kuyruk üzerinden gider: genellikle kuyruk bir sonraki çalıştığında, bir dakika içinde.',
    'queue-stalled' => ':minutes dakikadan uzun süredir kuyrukta. Kuyruk çalışıyor mu?',
    'trapped' => 'Bir test aktarımı tarafından kabul edildi',
];
