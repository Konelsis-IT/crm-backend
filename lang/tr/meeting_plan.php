<?php

return [
    'label' => 'Görüşme planı',
    'plural' => 'Görüşme planı',
    'label_title' => 'Görüşme Planı',
    'plural_title' => 'Görüşme Planı',

    'sections' => [
        'main' => 'Görüşme',
        'people' => 'Personel',
        'result' => 'Görüşme sonucu',
        'follow_up' => 'Hangi görüşmenin sonraki adımı',
    ],

    'fields' => [
        'party' => 'Firma / kurum',
        'contact' => 'Görüşülecek kişi',
        'planned_on' => 'Görüşme tarihi',
        'channel' => 'Kanal',
        'personnel' => 'Sorumlu personel',
        'participants' => 'Katılacak personel',
        'subject' => 'Konu / amaç',
        'note' => 'Not',
        'status' => 'Durum',
        'source' => 'Kaynak',
        'completed_at' => 'Sonucun girildiği an',
        'cancel_reason' => 'Neden gerçekleşmedi?',
        'follow_up_of' => 'Görüşme notunun tarihi',
    ],

    'help' => [
        'participants' => 'Görüşmeye birlikte gidecek personel. Hatırlatma bildirimi onlara da gider.',
        'note' => 'Hazırlık notu ya da görüşmenin neden gerçekleşmediği.',
        'complete' => 'Görüşmenin sonucu firmanın Görüşme notlarına yazılır. Sonraki adım tarihi verirseniz takvime yeni bir planlı görüşme olarak düşer ve hatırlatması gider.',
        'reschedule' => 'Yeni tarih için hatırlatma (1 gün önce ve günün sabahı) yeniden gider.',
        'cancel' => 'Görüşme "Gerçekleşmedi" olarak işaretlenir; yazdığınız neden plan notuna eklenir.',
        'result' => 'Bu bilgi firmanın Görüşme notlarındadır; düzeltmeyi oradan yapın.',
    ],

    'values' => [
        'overdue' => 'Gecikti',
        'no_personnel' => 'Atanmadı',
        'follow_up_subject' => 'Sonraki adım',
    ],

    'tabs' => [
        'upcoming' => 'Yaklaşan',
        'today' => 'Bugün',
        'overdue' => 'Geciken',
        'done' => 'Gerçekleşen',
        'cancelled' => 'Gerçekleşmeyen',
        'all' => 'Tümü',
    ],

    'filters' => [
        'personnel' => 'Personel (sorumlu ya da katılan)',
        'dates' => 'Tarih aralığı',
        'from' => 'Başlangıç',
        'until' => 'Bitiş',
    ],

    'actions' => [
        'open' => 'Aç',
        'create' => 'Görüşme planla',
        'calendar' => 'Takvim görünümü',
        'list' => 'Liste görünümü',
        'complete' => 'Sonucu gir',
        'reschedule' => 'Tarihi değiştir',
        'cancel' => 'Gerçekleşmedi',
    ],

    'messages' => [
        'completed' => 'Görüşme sonucu kaydedildi ve firmanın Görüşme notlarına yazıldı.',
        'rescheduled' => 'Görüşme tarihi değiştirildi.',
        'cancelled' => 'Görüşme gerçekleşmedi olarak işaretlendi.',
    ],

    // Zil bildirimi: 1 gün önce ve günün sabahı (MeetingReminderScanner).
    'notifications' => [
        'meeting' => [
            'day_before' => 'Yarın görüşmeniz var: :party',
            'same_day' => 'Bugün görüşmeniz var: :party',
        ],
        'follow_up' => [
            'day_before' => 'Yarın sonraki adım: :party',
            'same_day' => 'Bugün sonraki adım: :party',
        ],
    ],

    // Takvim ekranı (React) metinleri; sosyal medya takviminin ortak metinleriyle birleşir.
    'ui' => [
        'meeting_summary' => 'Bu ay :count görüşme',
        'meeting_day_label' => ':date, :count görüşme',
        'meeting_day_count' => ':count görüşme',
        'meeting_day_empty' => 'Bu günde görüşme yok.',
        'meeting_add_to_day' => 'Bu güne görüşme planla',
        'meeting_follow_up' => 'Sonraki adım',
        'meeting_no_personnel' => 'Atanmadı',
        'meeting_all_personnel' => 'Tüm personel',
        'meeting_personnel_filter' => 'Personele göre süz',
        'meeting_status_planned' => 'Planlı',
        'meeting_status_overdue' => 'Gecikti',
        'meeting_status_done' => 'Gerçekleşti',
        'meeting_status_cancelled' => 'Gerçekleşmedi',
    ],
];
