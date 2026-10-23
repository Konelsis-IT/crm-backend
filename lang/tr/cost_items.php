<?php

// D-189: teklif sayfasindaki Maliyet kalemleri sekmesi (CostItemsRelationManager).
return [
    'relation' => [
        'title' => 'Maliyet kalemleri',
        'empty' => 'Bu teklifin güncel sürümüne aktarılmış maliyet kalemi yok.',
        'empty_description' => 'Teklif kapsamına yüklenen maliyet listesini "Excel\'den aktar" ile kalemlere ayırabilirsiniz.',
    ],

    'tabs' => [
        'product_service' => 'Ürün/Hizmet',
        'admin_staff' => 'İdari kadro',
        'general_expense' => 'Genel giderler',
    ],

    'summary' => ':count kalem · :matched katalogda · :new yeni · :similar benzer (seçim bekliyor) · Toplam maliyet :total · Fiyatı netleşmeyen :open',

    'fields' => [
        'line_no' => 'No',
        'description' => 'Kalem',
        'match' => 'Katalog',
        'quantity' => 'Miktar',
        'unit_cost' => 'Birim maliyet',
        'total_cost' => 'Toplam maliyet',
        'total_sale' => 'Toplam satış',
        'price_state' => 'Fiyat durumu',
        'purchase_status' => 'Satın alma',
    ],

    'match' => [
        'similar' => 'Benzer: :name?',
        'new' => 'Yeni: :name',
    ],

    'filters' => [
        'with_cost' => 'Tutarı olanlar',
        'price_state' => 'Fiyat durumu',
        'match' => 'Katalog eşleşmesi',
        'not_sent' => 'Satın almaya gönderilmedi',
    ],

    'actions' => [
        'import' => 'Excel\'den aktar',
        'match' => 'Eşleştir',
        'send' => 'Satın almaya gönder',
    ],

    'import' => [
        'document' => 'Maliyet listesi',
        'description' => 'Teklif kapsamına yüklenen maliyet listesi kalemlere ayrılır. Her kalem katalogdaki var olan kalemle eşleşir; katalogda olmayan bir kez eklenir, çok benzeyen kalem seçiminizi bekler.',
    ],

    'match_form' => [
        'description' => 'Excel\'deki kalemi katalogdaki bir kaleme bağlayın. Bu yazım eşleme adı olur ve sonraki yüklemelerde kendiliğinden eşleşir.',
        'line' => 'Excel\'deki kalem',
        'catalog_item' => 'Katalog kalemi',
        'as_new' => 'Yeni katalog kalemi olarak ekle',
    ],

    'send_form' => [
        'description' => 'Seçilen kalemler sipariş kalemi olur ve taahhüt onayından sonra satın almanın listesine düşer. Tutarı olmayan, katalogla eşleşmeyen ya da daha önce gönderilmiş kalem atlanır.',
        'needed_by' => 'Termin',
        'is_urgent' => 'Acil',
        'note' => 'Not',
    ],

    'messages' => [
        'imported' => ':count kalem aktarıldı: :matched katalogda eşleşti, :new yeni, :similar benzer',
        'matched' => 'Kalem kataloğa bağlandı.',
        'sent' => ':created kalem satın almaya gönderildi (taahhüt onayı bekliyor); eşleşmeyen :unmatched, atlanan :skipped',
    ],
];
