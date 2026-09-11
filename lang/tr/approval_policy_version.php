<?php

return [
    'label' => 'Politika sürümü',
    'plural' => 'Politika Sürümleri',

    'relation' => [
        'title' => 'Sürümler',
        'empty' => 'Henüz sürüm yok. Yeni sürüm açın, adımlarını ekleyin ve yayımlayın.',
    ],

    'sections' => [
        'flow' => 'Akış',
        'threshold' => 'Tutar eşiği (isteğe bağlı)',
        'note' => 'Not',
    ],

    'fields' => [
        'version_no' => 'Sürüm',
        'status' => 'Durum',
        'mode' => 'Akış modu',
        'quorum_count' => 'Yeterli onay sayısı',
        'requires_maker_checker' => 'Talep sahibi onaylayamaz',
        'reapproval_on_change' => 'İçerik değişince yeniden onay',
        'sla_minutes' => 'Karar süresi (dk)',
        'risk_level' => 'Risk düzeyi',
        'applies_min_amount' => 'En düşük tutar',
        'applies_max_amount' => 'En yüksek tutar',
        'currency_code' => 'Para birimi',
        'change_summary' => 'Değişiklik özeti',
        'publisher' => 'Yayımlayan',
        'published_at' => 'Yayım tarihi',
        'steps_count' => 'Adım',
    ],

    'help' => [
        'relation' => 'Yeni sürüm taslak açılır; adımları eklendikten sonra yayımlanır. Yayımlı sürüm değiştirilemez, yenisi açılır.',
        'flow' => 'Adımların nasıl işleneceği ve görevler ayrılığı.',
        'mode' => 'Sıralı: adımlar sırayla. Paralel: hepsi aynı anda, hepsi tamamlanmalı. Nisap: aynı anda, belirlenen sayıda adım yeter.',
        'quorum_count' => 'Nisap modunda talebin onaylanması için tamamlanması gereken adım sayısı.',
        'requires_maker_checker' => 'Açıkken talep sahibi hiçbir adımda kendi talebini onaylayamaz (görevler ayrılığı).',
        'reapproval_on_change' => 'Onay sürerken konu değişirse talep geçersizleşir; yeni talep gerekir.',
        'sla_minutes' => 'Adımın kendi süresi yoksa bu süre kullanılır; süre geçince talep "süresi doldu" olur ve üst yönetim bilgilenir.',
        'threshold' => 'Tutarlı konularda (teklif, sipariş) politikanın hangi tutar aralığına uygulanacağı. Boş bırakılırsa her tutara uygulanır.',
        'publish' => 'Sürüm yayımlanınca önceki yayımlı sürüm yürürlükten kalkar ve politika aktif olur. Yayımlı sürüm değiştirilemez.',
    ],

    'actions' => [
        'create' => 'Yeni sürüm',
        'open_steps' => 'Adımları aç',
        'publish' => 'Yayımla',
    ],

    'messages' => [
        'published' => 'Sürüm yayımlandı; politika aktif.',
    ],
];
