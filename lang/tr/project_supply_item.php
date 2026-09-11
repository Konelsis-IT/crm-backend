<?php

return [
    'label' => 'Tedarik kalemi',
    'plural' => 'Tedarik kalemleri',

    'sections' => [
        'main' => 'Kalem bilgileri',
        'commercial' => 'Miktar ve maliyet',
        'dates' => 'Tarihler ve durum',
    ],

    'fields' => [
        'project' => 'Proje',
        'workstream' => 'Adım (departman)',
        'item_kind' => 'Tür',
        'item_code' => 'Kalem kodu',
        'name' => 'Ad',
        'specification' => 'Teknik özellik / açıklama',
        'quantity' => 'Miktar',
        'uom' => 'Birim',
        'unit_cost' => 'Birim maliyet',
        'currency' => 'Para birimi',
        'total_cost' => 'Toplam maliyet',
        'supplier' => 'Tedarikçi',
        'wbs_node' => 'İş kırılımı kalemi',
        'needed_on' => 'İhtiyaç tarihi',
        'ordered_on' => 'Sipariş tarihi',
        'expected_delivery_on' => 'Beklenen teslim',
        'delivered_on' => 'Teslim tarihi',
        'status' => 'Durum',
        'note' => 'Not',
        'created_at' => 'Oluşturulma',
        'reason' => 'Gerekçe',
    ],

    'help' => [
        'item_code' => 'Katalog gelene kadar serbest kod (örn. PV-550, INV-1500).',
        'currency' => 'Boş bırakılırsa projenin para birimi kullanılır.',
    ],

    'relation' => [
        'title' => 'Tedarik kalemleri',
        'empty' => 'Henüz tedarik kalemi yok.',
        'logistics_title' => 'Sevkiyat ve teslimat',
        'software_title' => 'Yazılım / otomasyon kalemleri',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu \":status\" yap',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'duplicate' => 'Bu kayıt zaten var.',
    ],

    'filters' => [
        'my_step' => 'Odağı satın almada olan projeler',
    ],
];
