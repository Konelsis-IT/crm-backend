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
    'record_archived' => 'Bu kayıt arşivde. Değiştirmek için önce arşivden çıkarın.',

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
        // D-175
        'coordinator_not_active' => 'Proje tipi koordinatörü olarak yalnız aktif bir personel seçilebilir.',
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
        'notification_scope_not_allowed' => 'Bu kitleye duyuru gönderme yetkiniz yok.',
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
    'party' => [
        'activity_area_parent_invalid' => 'Alt faaliyet alanı yalnız bir ana faaliyet alanının altına bağlanabilir; alt alanı olan bir ana alan başka bir alanın altına taşınamaz.',
        'meeting_note_proposal_mismatch' => 'Görüşme notuna yalnız seçilen potansiyel işin teklifleri bağlanabilir.',
        'party_merge_not_allowed' => 'Bu iki taraf birleştirilemez: iki farklı ve aynı türde (kuruluş ya da kişi) taraf seçilmeli; birleştirilmiş bir taraf yeniden birleştirilemez ve arşivdeki bir tarafa birleştirme yapılamaz.',
    ],
    'work_request' => [
        'message_empty' => 'Cevap boş; bir metin, bağlantı ya da dosya ekleyin.',
        'forward_not_allowed' => 'Talep bu kişiye ya da birime yönlendirilemez (mevcut muhatap, talep eden veya onay mercii seçilemez).',
        'target_required' => 'Talebin muhatabı (personel ya da departman) seçilmelidir.',
        'self_target' => 'Kendinize talep açamazsınız.',
        'note_required' => 'Ret için gerekçe zorunludur.',
        'approver_required' => 'Onaya tabi talepte onay mercii seçilmelidir.',
        'requester_cannot_handle' => 'Kendi açtığınız talebi kabul edemez, tamamlayamaz ya da reddedemezsiniz; bu işlemler muhataba aittir.',
        'approver_invalid' => 'Onay mercii talep eden ya da muhatap olamaz.',
        'approval_policy_missing' => 'Onaya tabi talep için ":policy" onay politikası tanımlı değil; ApprovalPolicySeeder çalıştırılmalıdır.',
    ],
    // D-189 satin alma
    // D-192 Konelsis AI baglantisi (app/Exceptions/AiService)
    'ai_service' => [
        'ai_service_not_configured' => 'Konelsis AI bağlantısı bu ortamda tanımlı değil.',
        'ai_service_unavailable' => 'Konelsis AI şu anda yanıt vermiyor; biraz sonra tekrar deneyin.',
        'ai_service_access_denied' => 'Konelsis AI bu işlem için erişim vermedi; bağlantı ayarlarını kontrol ettirin.',
        'ai_service_request_rejected' => 'Konelsis AI belgeleri kabul etmedi; belge türünü ve boyutunu kontrol edin.',
        'ai_service_limit_reached' => 'Konelsis AI\'nin günlük okuma sınırı doldu; yarın tekrar deneyin.',
        'ai_service_result_invalid' => 'Konelsis AI\'nin cevabı okunamadı; tekrar deneyin.',
    ],
    'procurement' => [
        'cost_list_format' => 'Excel maliyet listesi biçimi tanınmadı. "Birim_Fiyat" ile başlayan Ürün/Hizmet sayfası (ve "İdari Kadro ve Genel Giderler" sayfası) bulunmalıdır.',
        'cost_list_already_imported' => 'Bu maliyet listesi bu teklif sürümüne zaten aktarıldı.',
        'cost_line_not_matched' => '":line" kalemi katalogda eşleşmedi; önce benzer kalemlerden birini seçin ya da yeni kalem olarak ekleyin.',
        'purchase_need_closed' => 'Bu sipariş kalemi kapalı ya da arşivde; değiştirilemez.',
        'project_approval_pending' => 'Bu kalem henüz proje tarafından onaylanmadı; proje onayı olmadan satın almaya düşmez.',
        'purchase_approval_required' => 'Bu sipariş onaysız tamamlanamaz. Onaylaması gereken merci: :approvers.',
        'purchase_quote_required' => 'Önce bir tedarikçi teklifini önerilen teklif olarak seçin.',
        'purchase_quote_rate_required' => 'Dövizli teklifte TL kuru girilmelidir; onay sınırı TL ile karşılaştırılır.',
        'catalog_item_exists' => 'Katalogda ":name" adlı bir kalem zaten var.',
        'purchase_not_allowed' => 'Bu işlem için yetkiniz yok.',
        'catalog_item_required' => 'Bu talep katalogda olmayan bir ürün / hizmetle açıldı; önce "Katalogla eşleştir" ile bir katalog kalemine bağlayın.',
        'purchase_request_incomplete' => 'Satın alma talebinde ürün / hizmet (katalogdan ya da adıyla) ve gerekçe zorunludur.',
        'purchase_request_required' => 'Şirket içi alım bir satın alma talebine bağlı olmalıdır; önce talebi seçin.',
        // D-192 (B53)
        'purchase_quotation_locked' => 'Bu satın alma teklifi siparişe dönüştü, reddedildi ya da iptal edildi; değiştirilemez.',
        'purchase_quotation_not_selected' => 'Sipariş yalnız seçili satın alma teklifinden açılır; önce teklifi "En uygun teklif" olarak seçin.',
        'purchase_quotation_empty' => 'Teklifte fiyatı girilmiş kalem yok; önce kalem ve birim fiyat girin.',
        'purchase_need_not_quotable' => '":title" kalemi teklife eklenemez: proje onayı bekliyor, siparişe girmiş ya da kapalı.',
        'quotation_line_catalog_required' => '":line" kalemi Malzeme ve Hizmetler\'de eşleşmedi; önce listeden seçin ya da yeni kalem olarak ekleyin.',
        'purchase_order_closed' => 'Bu sipariş tamamlandı, iptal edildi ya da arşivde; değiştirilemez.',
        'purchase_order_exists' => 'Bu teklif zaten siparişe dönüştü.',
        'quotation_ai_no_readable_document' => 'Okunabilecek belge yok: PDF, Word (.docx), Excel (.xlsx) ya da fotoğraf yükleyin (belge başına en çok 10 MB).',
        'quotation_ai_busy' => 'Konelsis AI bu teklifin belgelerini zaten okuyor; okuma bitince tekrar deneyin.',
        // D-194 (B54): Ayarlar > Birimler
        'unit_exists' => '":name" adlı ya da bu türde ":symbol" simgeli bir birim zaten var.',
        'unit_dimension_locked' => 'Bu birim başka birimlerle dönüşüm bağıyla bağlı; türü değiştirilemez.',
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
        // Is panosu (B36, D-115)
        'control_section_not_found' => 'Kontrol bölümü bulunamadı.',
        'day_already_closed' => 'Bu gün kapatıldı: :no. Sonradan eklenen kartlar rapora kendiliğinden eklenir.',
        'week_already_closed' => 'Bu hafta kapatıldı: :no.',
        'waiting_source_required' => 'Bekleniyor durumundaki kartta işin bekleme sebebi seçilmelidir.',
        'invalid_parent' => 'Ana iş olarak kartın kendisi, bir alt kart ya da alt kartı olan kartın altı seçilemez.',
        'suggestion_unavailable' => 'Bu öneri artık kullanılamıyor; karta dönmüş ya da yoksayılmış olabilir.',
        'linked_record_not_found' => 'Bağlı kayıt bulunamadı.',
        'assignee_not_allowed' => 'Başkasına kart yazmak için o kişinin amiri ya da yetkili olmalısınız.',
        'week_summary_required' => 'Haftayı kapatmak için haftanın özetini yazın.',
    ],
    // Sosyal Medya (B31). Yer tutucular: urgent_not_allowed :days, urgent_cooldown :hours,
    // image_too_large :max (megapiksel), file_too_large ve body_too_large :max (okunur boyut, örn. "25 MB").
    'social_media' => [
        'decision_note_required' => 'Reddetme ve revize isteme kararında açıklama yazılmalıdır.',
        'not_approved' => 'Yalnız onaylanmış içerik paylaşıldı olarak işaretlenebilir.',
        'urgent_not_allowed' => 'Acil onay, plan tarihi belirlenmiş ve paylaşımına en fazla :days gün kalmış içerik için istenebilir.',
        'urgent_cooldown' => 'Bu içerik için kısa süre önce acil onay istendi. Yeniden istemek için :hours saat geçmesi gerekir.',
        'unsupported_media' => 'Dosya kabul edilmedi: türü desteklenmiyor, dosya boş ya da bozuk, ya da bu görsel için sürüm sınırına ulaşıldı.',
        'image_too_large' => 'Görsel işlenemeyecek kadar büyük. En fazla :max megapiksel görsel kullanılabilir; daha küçük bir dosya deneyin.',
        'media_not_image' => 'Bu işlem yalnız görsellerde yapılabilir.',
        'upload_session_invalid' => 'Yükleme oturumu geçersiz, süresi dolmuş ya da aynı anda açık yükleme sınırına ulaşıldı. Yüklemeyi baştan başlatın.',
        'content_archived' => 'Arşivdeki içerik değiştirilemez. Önce arşivden çıkarın.',
        'empty_content' => 'İçerik boş olduğu için onaylanamaz: fotoğraf içeriğine en az bir görsel, video içeriğine video dosyası ya da bağlantısı, metin içeriğine metin eklenmelidir.',
        'file_too_large' => 'Dosya çok büyük. İzin verilen en büyük boyut: :max',
        'body_too_large' => 'Metin çok uzun. İzin verilen en büyük boyut: :max',
        'urgent_no_recipient' => 'Acil onay bildirimi gönderilebilecek bir onaycı bulunamadı. Yöneticinize doğrudan haber verin.',
        'content_published' => 'Paylaşılmış içerik değiştirilemez. Düzenlemek için önce paylaşımı geri alın.',
    ],
    'platform' => [
        'invalid_release_version' => 'Sürüm numarası ":version" geçersiz. "2.4" ya da "2.4.1" biçiminde yazın.',
        'release_already_published' => ':version zaten canlıdaki yayın sürümü.',
        'release_downgrade' => ':version, canlıdaki :current sürümünden eski. Geri almak istiyorsanız komutu --geri-al seçeneğiyle çalıştırın; :current ile gelen özellikler yeniden kapanır.',
    ],
    // D-176: Tum belgeleri indir.
    'document' => [
        'bundle_empty' => 'Bu kayıtta indirebileceğiniz belge yok.',
        'bundle_not_created' => 'Belgeler ZIP dosyasına yazılamadı. Biraz sonra yeniden deneyin.',
    ],
];
