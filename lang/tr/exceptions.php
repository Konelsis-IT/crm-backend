<?php

/*
|--------------------------------------------------------------------------
| İş hatası mesajları
|--------------------------------------------------------------------------
|
| Anahtarlar exception sınıfının adından üretilir:
|
|   App\Exceptions\StaleRecordException              -> stale_record
|   App\Exceptions\Personnel\EmailAlreadyInUseException -> personnel.email_already_in_use
|
| Yeni bir exception eklerken sınıfa mesaj yazılmaz; karşılığı buraya
| ve lang/en/exceptions.php dosyasına eklenir.
|
*/

return [

    // Anahtarı bulunamayan hatalarda gösterilir.
    'generic' => 'İşlem tamamlanamadı. Lütfen tekrar deneyin.',

    'stale_record' => 'Bu kayıt siz açtıktan sonra başka biri tarafından değiştirildi. Sayfayı yenileyip tekrar deneyin.',
    'invalid_transition' => 'Bu durum değişikliğine izin verilmiyor.',
    'code_already_in_use' => 'Bu kod zaten kullanılıyor: :code',
    'record_not_found' => 'Kayıt bulunamadı.',
    'model_not_resolved' => 'Servisin çalışacağı model belirlenemedi: :service',
    'duplicate_record' => 'Bu kayıt zaten var.',

    'personnel' => [
        'email_already_in_use' => 'Bu e-posta adresi zaten kullanılıyor: :email',
        'self_parent_not_allowed' => 'Bir birim kendi üst birimi olamaz (doğrudan veya zincirleme).',
        'circular_manager_chain' => 'Bir personel, kendi altındaki birine doğrudan amir olarak bağlanamaz.',
    ],

    'acquisition' => [
        'handoff_not_acceptable' => 'Operasyona devir kabul edilemiyor: :reason',
        'guard_not_satisfied' => 'Bu adıma geçilemiyor: :reason',
    ],

    'project' => [
        'dependency_cycle' => 'Bu bağımlılık bir döngü oluşturuyor; izin verilmiyor.',
        'same_project_required' => 'Bağlanan kayıtlar aynı projeye ait olmalı.',
        'project_component_duplicate' => 'Bu bileşen projede zaten tanımlı; aynı bileşen ikinci kez eklenemez.',
        'allocation_exceeded' => 'Dağılım yüzdelerinin toplamı 100\'ü aşamaz (şu an :total).',
    ],

    'actor_required' => 'Bu işlem için oturum açmış bir personel gerekir.',

    'approval' => [
        'no_published_policy' => 'Bu konu için yayımlı bir onay politikası yok: :subject',
        'active_request_exists' => 'Bu kayıt için zaten açık bir onay talebi var.',
        'not_an_active_approver' => 'Bu adımda karar verme yetkiniz yok (onaycı değilsiniz, vekaletiniz yok ya da talep sahibisiniz).',
        'subject_changed' => 'Onay sürerken kayıt değişti; talep geçersizleşti. Yeni bir onay talebi açın.',
        'comment_required' => 'Ret ve iade kararında gerekçe zorunludur.',
        'request_not_decidable' => 'Bu talep artık karar kabul etmiyor (durum: :status).',
        'policy_has_no_steps' => 'Politika sürümünde hiç adım yok; yayımlamadan önce adım ekleyin.',
        'approver_unresolved' => '":step" adımı için onaycı bulunamadı: :reason',
        'self_delegation' => 'Kişi kendine vekalet veremez.',
        'version_not_draft' => 'Yalnız taslak sürüm düzenlenebilir (durum: :status).',
    ],

    'notification' => [
        'notification_scope_not_allowed' => 'Bu kitleye bildirim gönderme yetkiniz yok.',
        'no_recipients' => 'Seçilen kitlede size ek olarak aktif bir alıcı bulunamadı.',
        'alert_not_open' => 'Bu uyarı artık açık değil.',
    ],
    'chat' => [
        'not_a_member' => 'Bu sohbetin üyesi değilsiniz.',
        'empty_message' => 'Mesaj boş; bir metin, bağlantı, dosya ya da belge ekleyin.',
        'conversation_locked' => 'Bu sohbet kapalı; yeni mesaj gönderilemez.',
        'invalid_participant' => 'Seçilen kişiyle sohbet başlatılamıyor.',
        'document_not_shareable' => 'Belge bulunamadı ya da paylaşma yetkiniz yok.',
    ],
    'work_request' => [
        'target_required' => 'Talebin muhatabı (personel ya da departman) seçilmelidir.',
        'self_target' => 'Kendinize talep açamazsınız.',
        'note_required' => 'Ret için gerekçe zorunludur.',
        'approver_required' => 'Onaya tabi talepte onay mercii seçilmelidir.',
        'requester_cannot_handle' => 'Kendi açtığınız talebi kabul edemez, tamamlayamaz ya da reddedemezsiniz; bu işlemler muhataba aittir.',
        'approver_invalid' => 'Onay mercii talep eden ya da muhatap olamaz.',
        'approval_policy_missing' => 'Onaya tabi talep için ":policy" onay politikası tanımlı değil; ApprovalPolicySeeder çalıştırılmalıdır.',
    ],
    'report' => [
        'template_not_found' => 'Rapor taslağı bulunamadı: :code',
        'subject_required' => 'Bu taslak için ilgili kayıt seçilmelidir: :kind',
        'author_not_allowed' => 'Bu taslağı yazma yetkiniz yok: :template',
        'period_required' => 'Dönem seçilmelidir.',
        'invalid_period' => 'Bitiş tarihi başlangıç tarihinden önce olamaz.',
        'duplicate_period_report' => 'Bu dönem için zaten bir raporunuz var: :no',
        'review_comment_required' => 'Revizyon ve ret için açıklama zorunludur.',
        'report_locked' => 'Rapor bu durumda değiştirilemez: :status',
    ],
];
