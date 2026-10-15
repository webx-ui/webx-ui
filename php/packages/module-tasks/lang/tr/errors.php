<?php

declare(strict_types=1);

return [
    'agent-name' => 'Ajana bir ad verin: aşama ve günlük oturum adını kaydeder.',
    'project-archived' => ':project projesi arşivde: yeni görev yok ve hiçbir aşaması dağıtılmaz.',
    'task-closed' => 'Görev :status durumunda ve artık değiştirilemez.',
    'task-started' => 'Görev üzerinde çalışma başladı; yalnızca önceliği ve kimsenin almadığı aşamalar değişebilir. Değişemez: :fields.',
    'task-not-draft' => 'Yalnızca bir taslak çalışmaya alınabilir; bu görev :status durumunda.',
    'task-no-stages' => 'Aşaması olmayan bir görevin dağıtacak bir şeyi yok. En az bir aşama ekleyin.',
    'task-deleted' => 'Görev silindi.',
    'person-only' => 'Bir ajanın sorusunu yalnızca bir insan yanıtlayabilir.',
    'stage-elsewhere' => 'Burada adı geçen bir aşama başka bir göreve ait ya da iki kez geçiyor.',
    'last-stage' => 'Bu, kalan son aşama. Bunun yerine görevi iptal edin.',
    'stage-transition' => 'Bir aşama :from durumundan :to durumuna geçemez.',
    'stage-not' => 'Bunun için aşamanın :status olması gerekir; şu an :actual.',
    'not-holder' => 'Aşama :assignee üzerinde: yalnızca o ajan üzerinde çalışır.',
    'human-review' => 'Bu aşamayı bir insan kontrol eder: bir ajan onu ne kabul edebilir ne de geri gönderebilir.',
    'own-work' => 'Bir ajan kendi işini kontrol etmez.',
    'text-required' => ':what alanı boş olamaz.',
    'stage-missing' => 'Çalışma başladıktan sonra tüm aşamalar listede kalır; gereksiz bir aşama iptal edilir. Eksik: :stages.',
    'stage-new-at-end' => 'Yeni aşamalar sona eklenir.',
    'stage-started-order' => 'Başlamış ya da iptal edilmiş bir aşama yerinde kalır.',
    'stage-started' => ':stage aşaması :status durumunda ve artık değiştirilemez.',
    'notify-not-configured' => 'Bildirimler ayarlanmadı: bot token\'ını ve en az bir sohbeti ekleyip kaydedin.',
];
