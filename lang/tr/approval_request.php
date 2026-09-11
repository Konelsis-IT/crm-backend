<?php

return [
    'label' => 'Onay talebi',
    'plural' => 'Onay Talepleri',
    'nav' => 'Onaylar',

    'sections' => [
        'summary' => 'Talep',
    ],

    'tabs' => [
        'inbox' => 'Bana gelenler',
        'mine' => 'Taleplerim',
        'open' => 'Açık talepler',
        'all' => 'Tümü',
    ],

    'fields' => [
        'subject_type' => 'Konu türü',
        'subject_work_request' => 'Talep',
        'subject_document_revision' => 'Doküman revizyonu',
        'note' => 'Not',
        'subject' => 'Konu',
        'policy' => 'Politika',
        'requester' => 'Talep sahibi',
        'status' => 'Durum',
        'waiting_on' => 'Kimde',
        'requested_at' => 'Talep tarihi',
        'decided_at' => 'Karar tarihi',
        'invalidation_reason' => 'Geçersizlik nedeni',
        'note' => 'Talep notu',
        'reason' => 'Gerekçe',
        'comment' => 'Yorum / gerekçe',
        'approver' => 'Onaycı',
        'unresolved_reason' => 'Bulunamama nedeni',
        'step_status' => 'Adım durumu',
        'activated_at' => 'Aktifleşme',
        'due_at' => 'Son karar',
        'decider' => 'Karar veren',
        'on_behalf_of' => 'Adına',
        'decision' => 'Karar',
    ],

    'values' => [
        'unresolved' => 'Onaycı bulunamadı',
    ],

    'callouts' => [
        'open' => 'Karar bekleniyor — şu an: :who',
        'approved' => 'Talep onaylandı; konu güncellendi.',
        'rejected' => 'Talep reddedildi; gerekçe kararlarda.',
        'closed' => 'Talep kapandı: :status',
    ],

    'actions' => [
        'create' => 'Onay talebi aç',
        'open' => 'Aç',
        'approve' => 'Onayla',
        'reject' => 'Reddet',
        'return' => 'İade et',
        'cancel' => 'Talebi iptal et',
    ],

    'help' => [
        'create' => 'Seçilen kayıt için yayımlı politikaya göre onay talebi açılır; onaycılar politikadan çözülür.',
        'list' => 'Karar bekleyen talepler "Bana gelenler"de; kendi açtıklarınız "Taleplerim"de.',
        'comment_required' => 'Ret ve iadede gerekçe zorunludur.',
    ],

    'messages' => [
        'created' => 'Onay talebi açıldı ve onaycılar bilgilendirildi.',
        'link_invalid' => 'Bağlantının süresi dolmuş ya da bağlantı geçersiz.',
        'link_not_yours' => 'Bu bağlantı başka bir personele gönderilmiş.',
        'decided' => 'Kararınız kaydedildi.',
        'cancelled' => 'Talep iptal edildi.',
        'empty' => 'Henüz onay talebi yok.',
    ],

    'relation' => [
        'steps' => [
            'title' => 'Adımlar ve onaycılar',
        ],
        'decisions' => [
            'title' => 'Kararlar',
            'empty' => 'Henüz karar verilmedi.',
        ],
    ],

    'notifications' => [
        'step_activated' => [
            'title' => 'Onayınız bekleniyor',
            'body' => ':subject — adım: :step. Son karar: :due',
        ],
        'approved' => [
            'title' => 'Talebiniz onaylandı',
            'body' => ':subject onaylandı.',
        ],
        'rejected' => [
            'title' => 'Talebiniz reddedildi',
            'body' => ':subject reddedildi; gerekçe kararlarda.',
        ],
        'expired' => [
            'title' => 'Onay süresi doldu',
            'body' => ':subject için karar süresi geçti; talep kapandı.',
        ],
        'cancelled' => [
            'title' => 'Onay talebi iptal edildi',
            'body' => ':subject için talep iptal edildi.',
        ],
        'invalidated' => [
            'title' => 'Onay talebi geçersizleşti',
            'body' => ':subject onay sürerken değişti; yeni talep gerekir.',
        ],
    ],
];
