<?php

return [
    'label' => 'Teklif sürümü',
    'plural' => 'Teklif sürümleri',

    'sections' => [
        'main' => 'Sürüm bilgileri',
    ],

    'fields' => [
        'approved_at' => 'Onay tarihi',
        'created_at' => 'Oluşturulma',
        'currency' => 'Para birimi',
        'is_critical_route' => 'Kritik rota',
        'locale' => 'Dil',
        'margin_pct' => 'Marj (%)',
        'preparer' => 'Hazırlayan',
        'project_group_opinion_document_revision' => 'Proje Grubu görüşü',
        'proposal' => 'Teklif',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'submitted_at' => 'Gönderim tarihi',
        'summary' => 'Özet',
        'total_price' => 'Toplam fiyat',
        'validity_until' => 'Geçerlilik',
        'version_no' => 'Sürüm no',
        'scope_total_cost' => 'Kapsam toplam maliyeti',
        'scope_total_sales' => 'Kapsam toplam satışı',
    ],

    // D-155: marj kapsamdan hesaplanir.
    'help' => [
        'margin_from_scope' => 'Proje kapsamındaki toplam maliyet ve toplam satıştan hesaplanır.',
        'margin_from_scope_empty' => 'Kapsamda toplam maliyet ve satış girilince hesaplanır',
        'total_price_from_scope' => 'Boş bırakılırsa proje kapsamının toplam satışı yazılır.',
    ],

    'relation' => [
        'title' => 'Sürümler',
        'empty' => 'Henüz sürüm yok.',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu \":status\" yap',
        'select' => 'Seçili yap',
        'submit' => 'İncelemeye gönder',
        'review' => 'İnceleme kararı ver',
        'publish' => 'Yayımla',
        'approve' => 'Onayla',
        'change_focus' => 'Odağı değiştir',
        'waive' => 'Muafiyet ver',
        'add_evidence' => 'Kanıt ekle',
        'accept' => 'Kabul et',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
