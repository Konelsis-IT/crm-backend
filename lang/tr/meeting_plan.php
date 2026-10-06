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
        'archived_at' => 'Arşive alınma',
    ],

    'help' => [
        'participants' => 'Görüşmeye birlikte gidecek personel. Hatırlatma bildirimi onlara da gider.',
        'note' => 'Hazırlık notu ya da görüşmenin neden gerçekleşmediği.',
        'complete' => 'Görüşmenin sonucu firmanın Görüşme notlarına yazılır. Sonraki adım tarihi verirseniz takvime yeni bir planlı görüşme olarak düşer ve hatırlatması gider.',
        'reschedule' => 'Yeni tarih için hatırlatma (1 gün önce ve günün sabahı) yeniden gider.',
        'cancel' => 'Görüşme "Gerçekleşmedi" olarak işaretlenir; yazdığınız neden plan notuna eklenir.',
        'result' => 'Bu bilgi firmanın Görüşme notlarındadır; düzeltmek için üstteki "Notu düzenle"yi kullanın.',
        'past' => 'Tarihi geçti, sonucu girilmedi. Görüşme yapıldıysa "Sonucu gir", yapılmadıysa "Gerçekleşmedi" seçin.',
        'archive' => 'Görüşme silinmez, arşive alınır: takvimde, listede ve hatırlatmalarda görünmez. Liste ekranındaki Arşiv süzgeciyle bulunur ve geri alınabilir.',
        'archive_with_note' => 'Bu görüşmenin sonuç notu da (firmanın Görüşme notları) birlikte arşive alınır. Arşiv süzgeciyle bulunur ve geri alınabilir.',
        'restore' => 'Görüşme (ve onunla birlikte arşivlenen notu) yeniden takvimde ve listede görünür.',
    ],

    'values' => [
        // D-156: "Gecikti" yerine "Geçmiş": tarihi geçmiş, sonucu girilmemiş görüşme.
        'overdue' => 'Geçmiş',
        'no_personnel' => 'Atanmadı',
        'follow_up_subject' => 'Sonraki adım',
        'archived' => 'Arşivde',
    ],

    'tabs' => [
        'upcoming' => 'Yaklaşan',
        'today' => 'Bugün',
        'overdue' => 'Geçmiş',
        'done' => 'Gerçekleşen',
        'cancelled' => 'Gerçekleşmeyen',
        'all' => 'Tümü',
    ],

    'filters' => [
        'personnel' => 'Personel (sorumlu ya da katılan)',
        'dates' => 'Tarih aralığı',
        'from' => 'Başlangıç',
        'until' => 'Bitiş',
        'archive' => 'Arşiv',
        'archive_active' => 'Aktif',
        'archive_archived' => 'Arşivlenenler',
        'archive_all' => 'Tümü',
    ],

    'actions' => [
        'open' => 'Aç',
        'create' => 'Görüşme planla',
        'calendar' => 'Takvim görünümü',
        'list' => 'Liste görünümü',
        'complete' => 'Sonucu gir',
        'reschedule' => 'Tarihi değiştir',
        'cancel' => 'Gerçekleşmedi',
        'archive' => 'Arşive al',
        'restore' => 'Arşivden çıkar',
    ],

    'messages' => [
        'completed' => 'Görüşme sonucu kaydedildi ve firmanın Görüşme notlarına yazıldı.',
        'rescheduled' => 'Görüşme tarihi değiştirildi.',
        'cancelled' => 'Görüşme gerçekleşmedi olarak işaretlendi.',
        'archived' => 'Görüşme arşive alındı.',
        'restored' => 'Görüşme arşivden çıkarıldı.',
    ],

    // Görüşme planlarken firma listede yoksa "Firma ekle" penceresi (D-156).
    'quick_party' => [
        'action' => 'Firma ekle',
        'heading' => 'Firma ekle',
        'description' => 'Listede olmayan firmayı ekleyin; eklenen firma bu görüşmede seçili gelir. Diğer bilgileri sonra Taraflar ekranından tamamlayabilirsiniz.',
        'submit' => 'Ekle',
        'name' => 'Firma adı',
        'exists' => 'Bu adla kayıtlı bir firma var: :name. Firma alanında aratıp seçin.',
        'exists_archived' => 'Bu adla arşivde bir firma var: :name. Taraflar ekranında Arşiv süzgeciyle bulup arşivden çıkarın.',
        'created' => 'Firma eklendi: :name',
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
        'meeting_status_overdue' => 'Geçmiş',
        'meeting_status_done' => 'Gerçekleşti',
        'meeting_status_cancelled' => 'Gerçekleşmedi',
    ],
];
