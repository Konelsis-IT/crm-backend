<?php

return [
    'label' => 'Personel',
    'plural' => 'Personel',

    'sections' => [
        'identity' => 'Kimlik ve iletişim',
        'assignment' => 'Görev bilgileri',
        'competencies' => 'Yetkinlikler',
        'account' => 'Hesap ve giriş',
        'side' => 'Amir ve tarihler',
    ],

    'fields' => [
        'photo' => 'Fotoğraf',
        'full_name' => 'Ad Soyad',
        'personnel_no' => 'Sicil no',
        'national_id' => 'TC kimlik no',
        'phone' => 'Telefon',
        'whatsapp' => 'WhatsApp',
        'email' => 'E-posta',
        'department' => 'Departman',
        'direct_manager' => 'Doğrudan Amir',
        'job_title' => 'Görev',
        'hired_on' => 'İşe giriş tarihi',
        'competencies' => 'Yetkinlikler',
        'competency' => 'Yetkinlik',
        'competency_level' => 'Seviye',
        'competency_note' => 'Not',
        'locale' => 'Dil',
        'timezone' => 'Saat dilimi',
        'status' => 'Durum',
        'password' => 'Parola',
        'password_confirmation' => 'Parola tekrar',
        'current_password' => 'Mevcut parola',
        'password_changed_at' => 'Parola değişimi',
        'last_login_at' => 'Son giriş',
        'reason' => 'Gerekçe',
    ],

    'help' => [
        'national_id' => '11 haneli TC kimlik numarası.',
        'phone' => 'Cep telefonu; arama ve WhatsApp bağlantısı bu numaradan üretilir.',
        'status' => 'Bu alandan değişmez; sayfanın üstündeki "Durum değiştir" düğmesiyle güncellenir. Personel yalnızca "Aktif" durumda ve en az bir rolü varken panele girebilir.',
        'direct_manager' => 'Departman yöneticisinden ayrıdır; bu personelin fiilen raporladığı kişidir.',
        'password' => 'En az 8 karakter. Düzenlemede boş bırakılırsa parola değişmez.',
        'competencies' => 'Personelin yetkin olduğu konular; listede rozet olarak görünür.',
        'tap_to_call' => 'Numaraya tıklayınca arama başlar.',
        'profile_intro' => 'Kendi bilgileriniz. Değişiklikler Personel Hareketleri kaydına yazılır.',
        'profile_password' => 'Parolanızı değiştirmek istemiyorsanız bu alanı boş bırakın.',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu ":status" yap',
        'add_competency' => 'Yetkinlik ekle',
        'contact' => 'İletişim',
        'whatsapp' => 'WhatsApp ile yaz',
        'whatsapp_short' => 'Wp yaz',
        'start_chat' => 'Sohbet başlat',
        'call' => 'Ara',
        'mail' => 'E-posta gönder',
        'show_as_cards' => 'Kart görünümü',
        'show_as_list' => 'Liste görünümü',
    ],

    'messages' => [
        'status_changed' => 'Personel durumu güncellendi.',
        'no_competency' => 'Tanımlı yetkinlik yok.',
        'never_logged_in' => 'Hiç giriş yapılmadı',
        'profile_saved' => 'Profiliniz güncellendi.',
    ],

    'profile' => [
        'title' => 'Profilim',
    ],

    'validation' => [
        'email_taken' => 'Bu e-posta adresi zaten kullanılıyor.',
    ],
];
