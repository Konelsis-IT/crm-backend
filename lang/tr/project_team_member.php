<?php

return [
    'label' => 'Ekip üyesi',
    'plural' => 'Taahhüt ekibi',

    'sections' => [
        'main' => 'Görevlendirme',
    ],

    'fields' => [
        'project' => 'Taahhüt',
        'personnel' => 'Personel',
        'workstream' => 'Adım (departman)',
        'team_role' => 'Görev',
        'allocation_pct' => 'Ayrılan zaman (%)',
        'assigned_from' => 'Başlangıç',
        'assigned_until' => 'Bitiş',
        'is_lead' => 'Sorumlu',
        'status' => 'Durum',
        'note' => 'Not',
        'created_at' => 'Oluşturulma',
        'reason' => 'Gerekçe',
    ],

    'help' => [
        'allocation_pct' => 'Personelin bu taahhüde ayırdığı zaman payı.',
    ],

    'relation' => [
        'title' => 'Taahhüt ekibi',
        'empty' => 'Henüz ekip üyesi eklenmemiş.',
    ],

    'actions' => [
        'end' => 'Görevi bitir',
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu \":status\" yap',
    ],

    'messages' => [
        'done' => 'İşlem tamamlandı.',
        'ended' => 'Görevlendirme sonlandırıldı.',
        'status_changed' => 'Durum güncellendi.',
    ],

    'validation' => [
        'duplicate' => 'Bu personel aynı görevle zaten ekipte.',
    ],
];
