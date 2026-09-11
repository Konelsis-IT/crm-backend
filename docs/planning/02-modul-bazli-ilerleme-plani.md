# Konelsis Kurumsal Platform — Modül bazlı ilerleme planı

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. M01 tamamlandı; 7 Eylül 2026'da M02 (D-60…D-62), M03 (D-63…D-65), M04 (D-66) ve M08–M12 tam zinciri (D-67) kullanıcı talimatıyla erkene alınıp teslim edildi. 10 Eylül 2026'da M03'ün ikinci yarısı (onay motoru, D-76) ve M04'ün oluşturma/detay/yazma/paylaşım turu (D-75) teslim edildi; B06A/B07 migration'ları kullanıcı tarafından uygulanacak. M05–M07 ve M13+ ayrı yetkilendirme ister.  
**Sürüm:** 1.5 / 10 Eylül 2026

## 1. Teslim stratejisi

Geliştirme “ekranları hızlıca açma” sırasıyla değil, ortak veri ve süreç bağımlılıklarına göre yürütülür. Bir modül yalnız CRUD ekranları tamamlandığında bitmiş sayılmaz; yetki, audit, bildirim, doküman, hata/geri dönüş, performans ve manuel kabul senaryoları da tamamlanmalıdır.

| Dalga | Amaç | Modüller |
|---|---|---|
| P0 | Tasarımı dondurmak | M00 |
| R1 | Çalışan ve şirket içi çalışma temeli | M01–M07 |
| CF | Projeden bağımsız kurumsal fonksiyonlar | SM01–SM02; R1 çekirdeğinden sonra R2/R3 ile paralel |
| R2 | İş Alım zinciri | M08–M11 |
| R3 | Operasyon ve teslimat | M12–M18 |
| R4 | Kurumsal kontrol ve olgunlaşma | M19–M21 |

Takvim, ekip kapasitesi ve pilot kullanıcılar belirlenmeden ay/hafta tahmini sabitlenmez. Her dalganın süresi kabul kapılarından ve gerçek veri hazırlığından türetilir.

## 2. M00 — Planlama ve DB tasarım kapısı

**Amaç:** Uygulama başlamadan bütün ürün ve veri kararlarını onaylamak.

Teslimatlar:

- Gereksinim izlenebilirlik matrisi: ilk prompt, 13 görsel, şirket bilgi tabanı ve son kullanıcı kararları.
- Kurumsal terimler sözlüğü ve süreç sahipleri.
- Organizasyon/RACI, onay matrisi, vekâlet ve escalation matrisi.
- Rapor şablonu kataloğu ve iki gerçek pilot şablon.
- İş Alım ve Operasyon durum makineleri; handoff ve gate kriterleri.
- Kavramsal ERD, mantıksal model, veri sözlüğü, constraints ve read model sözleşmeleri.
- DMS/gizlilik/retention ve harici AI API sözleşmesi.
- Functional Area yönetişimi; Sosyal Medya RACI'si, hesap/platform envanteri, içerik/özel gün/KPI ve entegrasyon sözleşmesi.
- Teknik topoloji, manuel kabul planı ve DBA/DevOps şema prosedürü.

**Çıkış:** Beş plan ve ERD paketi onaylı, kritik/yüksek açık karar yok, DB-G8 ve DB-GSM tamamlanmış ve yetkili DBA/DevOps belirlenmiştir. Bu çıkıştan önce migration dahil uygulama kodu yazılmaz.

## 3. R1 — Çalışan ve şirket içi çalışma temeli

### M01 — Platform güvenlik temeli

**Bağımlılık:** M00.

Kapsam:

- Geliştirme/staging/production ayrımı ve MySQL 8.4 LTS/InnoDB, Redis ve object storage hazırlığı.
- Yanlışlıkla migration, Laravel testi veya Pint çalıştırabilen Composer/CI yollarının güvenli hâle getirilmesi.
- Personel Hareketleri kaydı, correlation ID, giden kutusu, temel numara serisi ve feature flag yaklaşımı.
- Strict authorization ve varsayılan-deny Policy iskeleti.

Çıkış kriteri:

- Yasak komutlar hiçbir standart setup/verify akışından dolaylı tetiklenemez.
- Uygulama şemayı kendi başına değiştiremez.
- Her iş işlemi personel veya sistem hesabı ile izlenebilir.

### M02 — Personel, organizasyon ve üst-alt ilişkisi

**Bağımlılık:** M01.

**Durum:** User/Personel ayrımı, personel numarası ve profil kartı zaten M01'de teslim edildi (D-42). Yetkinlik de M01'de vardı; sertifika ve eğitim kaydı D-60 ile 7 Eylül 2026'da erkene alınıp teslim edildi (bkz. 16 §4 B13, 17 D-60). Aynı gün organizasyon hiyerarşisi de D-62 ile **tam tasarımıyla** teslim edildi (16 §4 B03, 17 D-62) — `org_units` (eski `departments`'ın yerine), `org_unit_relations` (tarihçeli üst birim), `positions` (boş iskelet kadro kataloğu), `position_assignments`, `reporting_relationships` (tarihçeli doğrudan amir) ve Organizasyon Birimleri/Pozisyonlar ekranları + Personel altında üç yeni relation manager. Hassas profil, ayrılış ve vekâlet henüz başlanmadı. D-01 artık şema anlamında kapandı; yalnız gerçek pozisyon/organizasyon verisinin girilmesi (veri anlamında) açık kalmaya devam eder.

Kapsam:

- ~~User/Personel ayrımı, personel numarası ve profil kartı.~~ M01'de teslim edildi.
- ~~Organizasyon birimi, departman, pozisyon, lokasyon ve tarihçeli atama.~~ D-62 ile teslim edildi (lokasyon hariç — kapsam dışı bırakıldı, ihtiyaç doğarsa ayrı karar).
- ~~Doğrudan amir, fonksiyonel yönetici~~ ayrımı. D-62 ile `reporting_relationships` (doğrudan amir) ve `org_units.manager_personnel_id` (fonksiyonel yönetici) olarak teslim edildi. Proje yöneticisi ayrımı ileride (M12 proje modülüyle).
- ~~Yetkinlik, sertifika, eğitim~~, iletişim ve aktif görev özeti. Yetkinlik M01'de, sertifika/eğitim D-60 ile teslim edildi.
- Hassas personel profili, personel durumu ve ayrılış işlemleri.
- Süreli vekâlet, yedek sorumlu ve organizasyon değişiklik geçmişi. Organizasyon değişiklik geçmişi kısmen `org_unit_relations`/`personnel_assignments` ile karşılanıyor; vekâlet ve yedek sorumlu hâlâ açık.

Çıkış kriteri:

- ~~Aktif organizasyon ağacı ve geçmiş tarih görünümü doğrudur.~~ D-62 ile karşılandı (`org_unit_relations`, `personnel_assignments`).
- ~~Döngülü yönetici/birim ilişkisi kurulamamaktadır.~~ D-62 ile karşılandı (`OrgUnitService`/`PersonnelService` zincir kontrolü).
- ~~Departman değiştiren çalışanın eski rapor ve onay bağlamı bozulmamaktadır.~~ D-62 ile karşılandı (tarihçeli kayıtlar hiçbir zaman silinmez, yalnız kapatılır).
- Yönetici yalnız açıkça yetkili olduğu personel verisini görmektedir. Hâlâ açık; kalıcı rol/yetki şeması M03'ü bekliyor.

### M03 — Rol, kapsam ve onay altyapısı

**Bağımlılık:** M02.

**Durum:** RBAC çekirdeği D-63 ile 7 Eylül 2026'da teslim edildi (bkz. 16 §4 B05, 17 D-63) — `spatie/laravel-permission` paketi, `roles`/`permissions`/`model_has_roles`/`model_has_permissions`/`role_has_permissions` tabloları, Ayarlar altında Roller ekranı, Personel formunda rol ataması. `RoleResolver` artık `KONELSIS_SYSTEM_ADMIN_EMAILS` yerine gerçek role bakıyor. Onay altyapısı (matris, maker-checker, escalation) D-63'te dışarıda bırakılmıştı; **10 Eylül 2026'da D-76 ile teslim edildi** (bkz. 12 §2, 16 §4 B07, 17 D-76): `approval_policies/versions/steps`, `approval_requests/request_steps/decisions`, `delegations/delegation_snapshots`; sıralı/paralel/nisap akış, herhangi biri/hepsi/çoğunluk karar kuralı, maker-checker (talep sahibi hiçbir adımda karar veremez), karar anında konu hash doğrulaması, SLA süre aşımı + escalation zinciri (`konelsis:approvals:expire`, 15 dk), vekaletle karar; Ayarlar › Onay Politikaları, üst menüde Onaylar (bana gelenler/taleplerim), İdari › Vekaletler. İlk tüketici doküman revizyonu; teklif/sözleşme/devir/gate tablolarının `approval_request_id` bağları tüketici bağlanınca eklenir. Workflow tabloları (12 §1) hâlâ ertelidir.

Kapsam:

- ~~Global roller~~, departman/proje/veri sınıfı kapsamları. Global roller (system_admin, auditor) D-63 ile teslim edildi; departman/proje/veri sınıfı bazlı ince taneli kapsamlar henüz yok.
- Tutar, risk, kritiklik ve işlem türüne bağlı onay matrisi.
- Maker-checker, görevler ayrılığı, sıralı/paralel onay ve yeniden onay.
- Vekâlet ve boş pozisyonda escalation.

Çıkış kriteri:

- ~~Menü gizlenmesinden bağımsız servis ve Policy kontrolü vardır.~~ D-63 ile karşılandı — tüm Policy sınıfları `RoleResolver` üzerinden gerçek role bakıyor, menü gizlenmesi ayrı bir katman.
- ~~Talep sahibi, ayrılması gereken süreçte kendi son onayını veremez.~~ D-76 ile karşılandı (`requires_maker_checker`: talep sahibi adımlardan çıkarılır, yalnız kendisi kalıyorsa talep açılmaz; karar servisinde ikinci kontrol).
- ~~Onaylanan kayıt sürümü/hash'i karar ile birlikte kilitlenir.~~ D-76 ile karşılandı (`approval_requests.subject_hash`, her kararda `approved_subject_hash`; hash değişirse talep `invalidated`).

### M04 — Doküman Yönetim Sistemi (DMS)

**Bağımlılık:** M01, M03.

**Durum:** 7 Eylül 2026'da D-66 ile **tam kanonik tasarımıyla** (minimum çekirdek yerine) teslim edildi (bkz. 08 §1.1–1.16, 16 §4 B06, 17 D-66) — 16 tablo (`file_objects`, `document_types`, `documents`, `document_revisions`, `document_revision_files`, `document_links`, `document_reviews`, `transmittals`, `transmittal_items`, `document_templates`, `document_template_versions`, `generated_outputs`, `document_distributions`, `document_acknowledgements`, `legal_holds`, `legal_hold_documents`), 21 enum, 16 model, 16 Policy, 15 servis ve tam Filament ekranı: Doküman Tipleri ve Şablonlar (Ayarlar), Dokümanlar (revizyon/dosya yükleme + durum geçişi, bağlantı, inceleme, dağıtım, okundu-kabul teyidi alt listeleriyle), Teslim Tutanakları ve Hukuki Tutmalar (yeni "Belgeler" menü grubu). Onay motoru (M03/B07) henüz gelmediği için inceleme/onay kararları elle kaydedilir; `approval_request_id` alanları ertelenmiş FK olarak boş bırakıldı. Rapor/PDF üretim hattı (`generated_outputs`, `document_templates`) tablo ve ekranıyla hazır ama üretim motoru M05'i bekliyor. **Dosya katmanı (8 Eylül 2026, D-71):** yetki kontrollü indirme/önizleme uçları, güvenli türlerde satır içi gösterim, görsellerde otomatik küçük görsel türevi; revizyon ve fotoğraf listelerinde Önizle/İndir. Tür bazlı uzantı/boyut doğrulaması, depolama soyutlaması ve dosya yönetim ekranı sonraki tura kaldı. **Oluşturma/detay/yazma/paylaşım (10 Eylül 2026, D-75):** oluşturma formu gruplu bölümler (Kimlik · Sahiplik ve sınıflandırma · Bağlam · Belgenin aslı) ve belgenin aslı tek adımda yüklenir ya da sistemde yazılır (`content_kind = authored`, `body_html`, B06A); detay sayfası form değil çalışma alanıdır (doküman kartı, güncel içerik kartı, sekmeler; Yeni sürüm / Onaya gönder / Paylaş); paylaşım bağlantısı (`document_shares`, `/share/documents/{token}`, kimlik doğrulamasız ayrı panel) şimdilik yetkisiz erişime açıktır (E-09); indirme ucundaki hareket kaydı hatası düzeltildi. Onay motoru (D-76) ile revizyon onayı artık motordan geçer.

Kapsam:

- ~~Private dosya yükleme, dosya metadata'sı, MIME/boyut allowlist, checksum ve tarama durumu.~~ D-66 ile teslim edildi (`file_objects`, sha256 tekilleştirme; virüs tarama entegrasyonu henüz yok, `scan_status` "skipped" ile başlıyor).
- ~~Doküman kaydı/revizyonu, güvenlik sınıfı, erişim, indirme audit'i.~~ D-66 ile teslim edildi (`documents`/`document_revisions`, güvenlik sınıfı ve saklama politikası doküman tipinden miras alınır); indirme audit'i (dosyayı sunan imzalı URL/controller) henüz yok.
- Rapor eki, personel belgesi ve ileride proje/teklif eki için ortak güvenli dosya bağı. `document_links` tablosu ve ekranı hazır; hedef modüller (proje, teklif) henüz yok, `target_type`/`target_id` şimdilik serbest alan.
- Teslim tutanağı, dağıtım ve okundu/kabul teyidi. D-66 ile teslim edildi (`transmittals`, `document_distributions`, `document_acknowledgements`).
- Hukuki tutma (legal hold). D-66 ile teslim edildi (`legal_holds`, `legal_hold_documents`).

Çıkış kriteri:

- Yetkisiz kullanıcı dosyanın path'ini değiştirerek başka kayda erişemez. Policy katmanı hazır (`FileObjectPolicy` vd.); indirmeyi sunan imzalı URL/controller henüz yok, bu yüzden ölçülemez.
- ~~Gönderilmiş/onaylanmış revizyon overwrite edilmez.~~ D-66 ile karşılandı (`DocumentRevisionService::update()` yalnız taslak/incelemedeki revizyonlarda çalışır; değişiklik yeni revizyon açar).
- Karantinadaki dosya indirilemez veya iş kanıtı sayılamaz. Tarama motoru bağlanmadığı için henüz ölçülemez.

### M05 — Şirket geneli raporlama ve PDF

**Bağımlılık:** M02–M04.

Kapsam:

- Günlük, haftalık, aylık, özel ve olay bazlı şablonlar.
- Versiyonlu bölüm/alan/validation/ek kuralları.
- Personel, pozisyon, departman ve ekip hedefli takvim/assignment.
- Report occurrence, taslak, submit, manager review, revizyon ve arşiv.
- Şablon uyumu, geç/eksik rapor ve KPI read model'leri.
- TR/EN, Blade/HTML tabanlı immutable PDF çıktısı.

Çıkış kriteri:

- Aynı çalışan/şablon/dönem için duplicate rapor yükümlülüğü oluşmaz.
- Yayımlanmış şablonun yeni sürümü geçmiş raporu değiştirmez.
- Gönderilen rapor yerinde düzenlenemez; düzeltme yeni revision oluşturur.
- Seçilen dilde zorunlu alan eksikse PDF üretimi durur ve eksikler gösterilir.

### M06 — Bildirim, kritik iş ve harici rapor kontrolü

**Bağımlılık:** M03, M05.

Kapsam:

- Filament in-app notification, asenkron e-posta, read/acknowledge/resolve ayrımı.
- Rapor teslim hatırlatması, overdue ve manager escalation.
- Yönetici tarafından rapordan bağımsız personele bilgi/talep/uyarı/kritik bildirim.
- Alt personelden üst yönetime kritik iş/escalation.
- `AI ile Kontrol Et` Action'ı ve ayrı AI projesi API istemci sınırı.
- Şablon bazlı dış sonuç aksiyonları: öneri göster, revizyona döndür, kişiyi/yöneticiyi bildir, takip görevi aç.

Çıkış kriteri:

- Gecikmiş rapor doğru tarihsel manager snapshot'ına tek bildirim/escalation üretir.
- Kritik bildirim acknowledgment olmadan sessizce kapanmaz.
- Dış servis kesintisi rapor kaydetme ve manuel incelemeyi durdurmaz.
- Tekrarlanan callback ikinci sonuç, görev veya bildirim üretmez.
- Dış servis Policy ve application service sınırını atlayamaz.

### M07 — Tam personel/İK operasyonu ve kayıtlı iletişim

**Bağımlılık:** M02–M06.

Kapsam:

- İzin, devam, masraf, yetkinlik, sertifika, eğitim, atama ve performans.
- İş gücü ihtiyacı, iç atama seçeneği, ilan sürümü, aday/CV, mülakat ve işe giriş handoff'u.
- Şirket, departman, grup ve bire bir Filament-native konuşma dizileri.
- Mesaj, mention, belge/link, okundu ve mesajdan görev/karar/kritik iş üretme.

Çıkış kriteri:

- Aday/CV, ücret, sağlık ve performans verileri dar yetki alanındadır.
- İşe alma kararı ile muhasebe/bordro açılış handoff'u ayrıdır.
- Üst-alt ilişkisi özel mesajları otomatik görünür yapmaz.
- Sohbet mesajı onay veya resmî doküman kabulü sayılmaz.

R1 kabul edildiğinde personel, genel rapor, uyarı, dış rapor kontrolü ve şirket içi kayıtlı iletişim proje modülünden bağımsız çalışır.

## 4. R2 — İş Alım zinciri

### M08 — Firma, kişi ve kurumsal ana veri

**Bağımlılık:** R1 DMS/yetki temeli.

**Durum:** 7 Eylül 2026'da D-67 ile M08–M12 tam zinciri birlikte teslim edildi (bkz. 10 §1, 16 §4 B16, 17 D-67) — `parties` (kuruluş/kişi subtype exact-one), tarihçeli `party_roles` (rol başına tek aktif guard), adres/iletişim noktası/kişi ilişkisi, lisans/sertifika (süre durumu otomatik), yıllık değerlendirme; `PartyService` party_no, normalize ad ve tekilleştirme hash'i üretir. 360 ekranı ve veri kalite kuralları temel düzeyde (dedupe hash uyarısı servis katmanında); merge akışı henüz yok. **Ekran (8 Eylül 2026):** yeni "İş Alım" menü grubunda Taraflar (UI adı; teknik ad `parties`) — roller, adresler, iletişim noktaları, kişiler, lisanslar, sertifikalar ve yıllık değerlendirmeler alt listeleriyle.

Kapsam:

- Tek Party ana kaydı; müşteri, tedarikçi, taşeron, partner, yatırımcı ve işveren rolleri.
- Kişi/kuruluş ilişkisi, adres, iletişim, ülke varlığı, lisans/yeterlilik ve yıllık takip.
- Müşteri/firma 360 ekranı ve veri kalite/tekilleştirme kuralları.

Çıkış kriteri: Aynı firma farklı rollerde kopyalanmaz; rol ve ilişki geçmişi korunur.

### M09 — İş Geliştirme ve ihale alımı

**Bağımlılık:** M08, M04–M06.

**Durum:** D-67 ile teslim edildi (10 §2, 16 §4 B16) — `business_cases` + global sıra + `TKLF-n` kodu tek transaction'da (`BusinessCaseService`), 1:1 fırsat ve aşama geçmişi (SM-BC/SM-OPP), iş geliştirme aktiviteleri ve katılımcıları, ihale kaynağı kataloğu, ilan + sürüm (aynı hash yeni sürüm üretmez) + şart + son tarih (UTC hesaplı). Kurumsal teklif mailbox'ı ve e-posta dedupe M07/B12 (iletişim) ile; scraping kurum onayı bekliyor. **Ekran (8 Eylül 2026):** İş Alım › İş Dosyaları (UI adı; teknik ad `business_cases`; aşama geçişleri sayfa üstünde; fırsat, aktiviteler, ihale ilanları, teklifler, sözleşmeler ve Operasyona devir alt listeleri), İş Alım › İhale İlanları → İlan Sürümleri (şartlar, son tarihler), Ayarlar › İhale Kaynakları. **Zincir sihirbazı (9 Eylül 2026, D-72):** İş Dosyası oluşturma, düzenleme ve görüntüleme aynı üç adımı kullanır — İş dosyası → Teklif → Proje. Oluşturmada ilk teklif ve taslak sürümü (isteğe bağlı) ve kazanılmış iş için hemen projeye dönüşüm (isteğe bağlı) `AcquisitionIntakeService` ile tek transaction'da yazılır; görünümde iş dosyası kartı (bağlantılı müşteri/sahip) ve "şu an hangi aşamada / sıradaki aşama" uyarısı; Teklifler alt listesi 2. adımdadır.

Kapsam:

- Kurumsal teklif mailbox'ı, manuel giriş ve hukuken/teknik olarak izin verilen ihale kaynakları.
- E-posta Message-ID/hash ile dedupe, attachment DMS aktarımı ve işlem hata/retry görünümü.
- Fırsat/ihale, müşteri portföyü, toplantı, ziyaret, aktivite, pazar/ülke ve Bid/No-Bid.
- İlk değişmez sıra numarası ve `TKLF-n` kodu.

Çıkış kriteri:

- Her kaynak kayıt tekilleştirilmiş, sahipli ve deadline'lıdır.
- İş Geliştirme kapanış checklist'i tamamlanmadan Teklif handoff'u kabul edilemez.
- Scraping yalnız kaynak kullanım şartı ve kurum onayı sonrasında açılır.

### M10 — Teklif, fiyatlandırma ve onay

**Bağımlılık:** M09, M03–M04.

**Durum:** D-67 ile teslim edildi (10 §3, 16 §4 B16) — business case başına 1:N teklif + tek seçili (D-29), immutable teklif sürümleri (SM-PROP: approved'da `version_hash` kilidi, önceki sürümler superseded; submitted'da gönderim kanıtı/kanalı), teklif dokümanları (DMS revizyonları), uygunluk/sapma/marka/sorumluluk matrisi, tahmin sürümü + satır (toplamlar otomatik) + fiyat senaryosu (tek seçili) + BOQ. Onay isteği (`approval_request_id`) B07 onay motoru gelene kadar ertelendi; final fiyat onayı `changeStatus(approved)` ile elle verilir. **Ekran (8 Eylül 2026):** İş Alım › Teklifler (seçili teklif işlemi) → Teklif Sürümleri (durum geçişleri ve gönderim; dokümanlar, şartname uygunluğu, sapmalar, marka listesi, sorumluluk matrisi, maliyet tahminleri) → Maliyet Tahminleri (satırlar, fiyat senaryoları ve seçimi, BOQ; onay işlemi). Teklif, İş Dosyası zincir sihirbazının 2. adımında açılır (D-72); Teklif görünümündeki "Projeye dönüştür" korunur.

Kapsam:

- Teknik/ticari teklif, estimate/BOQ, maliyet, fiyat, marj ve fizibilite sürümleri.
- Şartname requirement/compliance/deviation; marka ve sorumluluk matrisi.
- Tedarikçi teklifleri, Excel, saha keşfi/fotoğrafı, rapor ve KMZ arşivi.
- Taslak takvim/MS Project alışverişi için adapter sınırı.
- Kritik proje rotası, Proje Grubu görüşü, final fiyat ve yayımlama onayı.

Çıkış kriteri:

- Yalnız onaylı ve immutable teklif sürümü gönderilebilir.
- Final sürüm değişirse eski onaylar geçersizleşir ve yeni sürüm tekrar onaya gider.
- Müşteriye gönderim kanıtı ve teslim zamanı kaydedilir.

### M11 — Sözleşme ve tek yönlü Operasyon devri

**Bağımlılık:** M10.

**Durum:** D-67 ile teslim edildi (10 §4–5, 14 §2.23–2.24, 16 §4 B16) — sözleşme/LOI/NTP kökü ve sürümleri (SM-CONTR; executed'da business case `won`), taraflar/dokümanlar/yükümlülükler/kilometre taşları; `operation_handoffs` (business case başına tek), sürüm + baseline snapshot + hash + standart kontrol listesi maddeleri, submit guard'ı, Proje Grubu incelemesi ve **atomik kabul transaction'ı** (`OperationHandoffService::accept`: PRJ-n kodu — ikinci üretim `uk_business_codes_case_kind` ile reddedilir —, proje + workstream + gate instance'ları, business case `operation` segmenti; hata hâlinde hiçbiri kalmaz). D-10 istisnası `CONTRACT` maddesinin gerekçeli muafiyeti ile. **Ekran (8 Eylül 2026):** İş Alım › Sözleşmeler → Sözleşme Sürümleri (durum geçişleri; taraflar, dokümanlar, yükümlülükler, kilometre taşları), İş Alım › Operasyona Devirler (kabul işlemi sayfa üstünde; kabulde Projeler grubuna geçilir) → Devir Sürümleri (kontrol listesi, submit, Proje Grubu incelemesi).

Kapsam:

- LOI/NTP/sözleşme, sözleşme sürümü, yükümlülük, ödeme/milestone ve sapmalar.
- Operasyona hazır checklist, doküman manifesti ve Proje Grubu kabulü.
- Atomik `TKLF-n → PRJ-n` dönüşümü ve immutable devir snapshot'ı.
- Kayıp/iptal analizi; Operasyonda ayrı change/variation/commercial clarification.

Çıkış kriteri:

- `TKLF-5` yalnız `PRJ-5` üretebilir; ikinci dönüşüm reddedilir.
- Dönüşüm hatasında kısmi proje, kod veya handoff kalmaz.
- Operasyondan İş Alım'a dönüş yoktur; eski kod ve teklif geçmişi salt okunur erişilebilir.

## 5. R3 — Operasyon ve teslimat

### M12 — Proje/Operasyon orkestrasyonu

**Bağımlılık:** M11.

**Durum:** D-67 ile teslim edildi (11 §1–3, 14 §2.25–2.28, 16 §4 B17) — PRJ kartı (yalnız devir kabulünde doğar), proje bileşenleri, altı workstream (D-22 varsayılan FS hard zinciri; SM-WS: ready guard'ı, blokaj gerekçesi, tamamlanınca successor'lar hazır), primary focus geçmişi (tek açık focus guard'ı, geri yönde gerekçe), `generic` G0–G7 stage-gate şablonu + sürüm yayımı + instance/requirement snapshot'ları (SM-GATE: predecessor gate'ler, zorunlu gereksinimler, kanıt = DMS revizyonu, inceleme kararı, muafiyet), departman devri (sürüm/manifest/inceleme; kabulde hedef workstream hazır), WBS/CBS (aynı proje, döngü/level, dağılım ≤ 100), iş paketi + bağımlılıklar, takvim baseline (onayda önceki superseded), kilometre taşı, ilerleme fotoğrafı, issue/risk (skor DB'de)/gecikme + telafi/değişiklik/ticari açıklama/maruziyet/karar (proje içi numaralar). Proje günlük/haftalık raporlarının M05 motoruna bağlanması M05 ile; `project_tasks` B11 `tasks` ile. **Ekran (8 Eylül 2026):** "Projeler" menü grubunda Projeler (yalnız devir kabulüyle doğar; oluşturma düğmesi yok; durum ve odak geçişleri sayfa üstünde) — bileşenler, workstream'ler, odak geçmişi, stage-gate instance'ları (gereksinim/kanıt/inceleme/muafiyet), departman devirleri (sürüm/manifest/inceleme), WBS/CBS, iş paketleri ve bağımlılıkları, takvim baseline, kilometre taşları, ilerleme fotoğrafları, issue/risk/gecikme (+telafi), değişiklik/ticari açıklama/maruziyet, kararlar ve projeye bağlı dokümanlar alt listeleriyle; Ayarlar altında Proje Bileşenleri, Operasyon Grupları ve Stage-Gate Şablonları (sürüm yayımı, gate tanımları, gereksinimler, bağımlılıklar). Belge ve Teslim Tutanağı formları `project_id` ile projeye bağlanır; böylece M05 raporlama projeden dallanabilir. **Çalışma alanı (8 Eylül 2026, D-68):** proje görüntüleme sayfası artık ayrı bir çalışma alanıdır — proje kartı (kapak fotoğrafı, PRJ kodu, durum/kaynak rozetleri, tam saha adresi), "şu an / sırada / eksik" uyarısı, altı adımlı stepper ve üst sekmelerde her departmanın kendi adım tasarımı (odak beklentisi kontrol listesi + doğrudan ekrandaki tablolar: dokümanlar, fotoğraflar, WBS, tedarik kalemleri, sevkiyat, ekip, iş paketleri, ilerleme...). Proje üç yoldan doğar: devir kabulü, tekliften dönüştürme (Teklif ve İş Dosyası görünümündeki "Projeye dönüştür") ve doğrudan oluşturma (geçmiş/aktif projeler için sihirbaz). Yeni "Satın Alma" menü grubunda Tedarik Kalemleri masası; Ayarlar › Odak Beklentileri (16 §4 B17A, 17 D-68). **Sihirbaz birliği (9 Eylül 2026, D-72):** proje oluştur = düzenle = çalışma alanı adımları (`App\Filament\Support\ProjectWizard`). Düzenleme sayfası oluşturma sihirbazının dört temel adımı + projenin departman adımlarını (beklenti listesi ve tablolar adım içinde) taşır, projenin bulunduğu adımdan açılır ve her adım serbestçe seçilir; çalışma alanında kart dizisi ve üst sekmeler korunur, yalnız Genel bakış'taki ayrı "Adım adım durum" listesi kaldırıldı (beklenti listesi her adımın sekmesinde; sekme gövdesi düzenleme sihirbazının departman adımıyla ortak). Proje kartı: kalın etiket, simge, renk; müşteri ve proje yöneticisi kendi kartlarına bağlanır; adrese tıklanınca saha adresi modalı açılır. **Kontrol listesi (9 Eylül 2026, D-73):** "Bu adımda beklenenler" numaralı satırlar; her satırda "+" (tablo beklentilerinde ilgili tablonun "Oluştur" eylemini açar, proje alanı beklentilerinde adres/tarih modalı ya da sihirbaz adımı) ve kalem (tabloya git / düzenle). Aynı kararla panel genelinde görüntüleme sayfalarındaki alt tablolar salt okunur olmaktan çıkarıldı (Filament varsayılanı kapatıldı; Policy'ler geçerli) ve çalışma alanında mevcut adımın tabloları sayfayla birlikte, diğerleri arka planda yüklenir.

Kapsam:

- PRJ kartı/tablosu, kapak görseli, proje bileşenleri, WBS/CBS, baseline ve milestone.
- Proje, Satın Alma, Muhasebe, Yazılım, Lojistik ve Saha workstream'leri.
- Bir primary focus + paralel aktif workstream'ler.
- Dependency, gate, kanıt, handoff, SLA, waiver, task, note, issue, risk, delay, change ve ticari maruziyet.
- Proje günlük/haftalık raporlarının M05 motoruna bağlanması.

Çıkış kriteri:

- Hard dependency yalnız bağlı işi bloke eder; bağımsız işler ilerler.
- Primary focus değişimi diğer workstream'leri kapatmaz ve geçmişte saklanır.
- Zorunlu kanıt/onay olmadan gate geçmez.
- Koşullu geçiş risk sahibi, gerekçe ve telafi tarihi olmadan açılamaz.

### M13 — Satın Alma

**Bağımlılık:** M12, M08, M04.

Kapsam:

- Proje/ofis talebi, teknik şartname/BOM, gerekli tarih ve maliyet kodu.
- Supplier RFQ, teklif sürümü, teknik/ticari karşılaştırma, approval ve PO.
- Tedarikçi değerlendirmesi ve proje maliyet/commitment handoff'u.

Çıkış kriteri: Teknik/bütçe onayı olmayan talep siparişe dönüşmez; limit üstü talep doğru onaycıya gider.

### M14 — Muhasebe ve proje finans kontrolü

**Bağımlılık:** M12–M13.

Kapsam:

- Bütçe, commitment, gerçekleşen maliyet referansı, gelir-gider ve nakit tahmini.
- PO–mal kabul–fatura eşleştirme, vergi/ödeme tarihi ve personel finansal açılış handoff'u.
- Zirve harici sistem kimlikleri ve sonraki entegrasyon adaptörü.

Çıkış kriteri:

- Muhasebe işe alma kararı vermez; yalnız onaylı işe girişin finansal/resmî işini alır.
- Uyuşmayan PO/teslim/fatura ödeme onayına ilerlemez.
- Resmî yevmiye CRM'de çoğaltılmaz.

### M15 — Lojistik, depo ve stok

**Bağımlılık:** M13–M14.

Kapsam:

- Ürün kataloğu, kategori, marka, görsel ve tekrarlanan ürün ana kaydı.
- Sevkiyat, taşıyıcı, gümrük, ETA/gerçek teslim, eksik/hasar ve alıcı.
- Depo/lokasyon/bin, rezervasyon, transfer, sayım, lot/seri ve immutable stok hareketi.

Çıkış kriteri: Siparişten saha teslimine ürün izi ve seri/lot sorumluluğu kaybolmadan izlenir.

### M16 — Yazılım, elektrik/otomasyon ve teknik işler

**Bağımlılık:** M12–M15.

Kapsam:

- Yazılım Grubu adı altında teknik ihtiyaç, elektrik altyapı, SCADA/EMS, panel, fiber/kamera ve saha teknik kontrolü.
- Teknik ihtiyaçtan satın alma talebi; arıza, panel değişikliği ve project change handoff'u.
- Mühendislik deliverable, çizim/revizyon ve standard/prosedür bağı.

Çıkış kriteri: Teknik değişiklik baz çizgiyi doğrudan değiştirmez; Proje Grubu change değerlendirmesine gider.

### M17 — Saha, Kalite ve İSG

**Bağımlılık:** M12, M15–M16, M05.

Kapsam:

- Work package, ekip/personel/ekipman, plan/prosedür/sözleşme dağıtımı ve kişi bazlı kabul.
- Günlük/haftalık saha raporu, fotoğraf/video, metraj, ilerleme ve deadline.
- Inspection, NCR, punch, incident/near miss, izin ve düzeltici faaliyet.

Çıkış kriteri:

- Saha işi gerekli onaylı doküman, yetkin ekip, malzeme ve izin olmadan hazır görünmez.
- Saha günlükleri genel rapor motorundan üretilir.
- Kritik gecikme/İSG olayı doğru acknowledgment/escalation zincirini tetikler.

### M18 — Test, devreye alma ve kabul

**Bağımlılık:** M16–M17.

Kapsam:

- Test plan/prosedür sürümü, test ekipmanı ve kalibrasyon.
- Çok aşamalı test, ölçüm, kanıt, witness/hold point, arıza/NCR/change.
- Günlük test raporu, final commissioning raporu, as-built/O&M ve kabul paketi.

Çıkış kriteri:

- Zorunlu ölçüm/kanıt ve yetkili onay olmadan test adımı veya kabul gate'i geçmez.
- Dış AI servisi kanıt/format değerlendirmesi yapabilir; yalnız capability matrisinde izinli rapor aksiyonunu uygular.
- Geçici/kesin kabul ve açık punch kayıtları izlenir.

## 6. R4 — Kurumsal kontrol ve olgunlaşma

### M19 — Garanti, bakım ve servis

**Bağımlılık:** M18.

- Kurulu varlık/tag, garanti, servis talebi, iş emri, ziyaret, bakım planı ve yedek parça.
- Proje handover dosyası kurulu varlık/servis kaydına kontrollü dönüşür.

### M20 — Yönetim raporlama ve ileri analitik

**Bağımlılık:** Yeterli doğrulanmış operasyon verisi.

- Portföy, pipeline, rapor uyumu, onay SLA, proje sağlık, maliyet, nakit, risk, stok ve kalite KPI'ları.
- KPI tanımı, veri kaynağı, sahibi ve hesaplama sürümü olmadan yönetim göstergesi yayımlanmaz.

### M21 — Entegrasyonlar ve gelecekteki modüller

- Zirve entegrasyonu, kurumsal e-posta, MS Project veri alışverişi, mevcut dosya sunucusu aktarımı ve read-only SCADA/KPI.
- Sunucu yönetimi ayrı güvenlik ve kapsam çalışması sonrası genişleme modülüdür; secret/credential DMS'de tutulmaz.
- AI personel klonu dahil bütün ileri AI kabiliyetleri ayrı projededir; CRM yalnız ayrıca onaylı API capability'sini sunar.

## 7. CF — Projeden bağımsız kurumsal fonksiyon hattı

Bu hattın modül kodları mevcut M-serisini kaydırmaz. `SM01` ve `SM02`, proje veya İş Alım kaydı olmadan çalışır; ortak R1 çekirdeğine bağımlıdır ve R2/R3 ile paralel planlanabilir.

### SM01 — Functional Area ve Sosyal Medya operasyon çekirdeği

**Bağımlılık:** M02 Personel/Organizasyon, M03 Yetki/Onay, M04 DMS ve M06 Bildirim/Kritik İş.

Kapsam:

- `social_media` Functional Area kaydı; tarihçeli owner/team/personel/pozisyon rolleri.
- İleride efektif tarihli OrgUnit sahipliğine geçiş ve açık sorumluluk transferi.
- Resmî sosyal hesap envanteri, hesap sahipliği, doğrulama ve manuel/connected çalışma modu.
- Kampanya, içerik brief'i, TR/EN immutable sürümler, hedef hesaplar ve DMS varlıkları.
- İçerik review/approval, planlanan yayın zamanı, manuel yayın URL/kanıtı ve düzeltme geçmişi.
- Doğrulanmış özel gün kataloğu ve yıllık occurrence'lar.
- Varsayılan T−4 hatırlatma; sorumlu ve en az taslak readiness yoksa T−3 kritik eskalasyon.
- Manuel metrik snapshot'ları ve temel hesap/içerik/özel gün dashboard'ları.

Çıkış kriterleri:

- Sosyal Medya departmanı veya proje kaydı olmadan uçtan uca içerik planı yürür.
- Creator/reviewer/approver/publisher ayrılığı capability ve Policy ile korunur.
- Onaylı exact sürümden farklı içerik planlanamaz/yayımlandı olarak kaydedilemez.
- T−4/T−3 olayları idempotenttir; boş/pasif rol nedeniyle bildirim kaybolmaz.
- Departmanlaşma provası eski kayıt kimliği, owner snapshot'ı ve audit geçmişini değiştirmez.

### SM02 — Sosyal platform entegrasyonları ve gelişmiş analitik

**Bağımlılık:** SM01, M01 kuyruk ve dış servis temeli ile platform bazlı fizibilite/onay.

Kapsam:

- Hesap bazlı `profile.read`, `metrics.read`, `content.publish`, `schedule.publish` ve `webhook.receive` capability matrisi.
- Secret manager referansı, OAuth scope/expiry metadata'sı ve bağlantı sağlık kontrolleri.
- Önce read-only profil/metrik adapter'ları; ayrı güvenlik onayı alınan hesaplarda publish adapter'ı.
- Kuyruk ile idempotent publish, retry/backoff/rate-limit, webhook signature/replay koruması ve reconciliation.
- Platform metric mapping'leri, ingestion batch/observation kayıtları, KPI formül sürümleri ve performans read model'leri.

Çıkış kriterleri:

- API olmayan veya bağlantısı kesilen hesapta SM01 manuel akışı çalışmaya devam eder.
- Aynı publish idempotency key veya webhook event'i ikinci paylaşım/metric üretmez.
- Harici post ID/URL veya yetkili manuel kanıt olmadan içerik `published` olmaz.
- Credential/token DB, DMS, log, bildirim veya export içinde bulunmaz.
- Aynı adlı platform metrikleri mapping/version olmadan tek KPI gibi birleştirilmez.

## 8. Paralel geliştirme sınırları

- R1 içindeki M04 teknik dosya güvenliği, M02–M03 sözleşmeleri sabitlenince paralel hazırlanabilir; M05 kabulü hepsine bağlıdır.
- M13 Satın Alma, M14 Muhasebe ve M16 Teknik İşler M12 sözleşmeleri dondurulduktan sonra paralel ilerleyebilir.
- M15 Lojistik M13 sipariş/katalog sözleşmesine; M17 Saha ise M15 malzeme ve M16 teknik teslimata bağlıdır.
- M18 Test/Devreye Alma, M16 ve M17'nin ortak kapanışıdır.
- SM01, M02–M04 ve M06 sözleşmeleri dondurulunca R2/R3'ten bağımsız başlayabilir; SM02 platform/hesap bazında ayrı kabul edilir.
- M20 yönetim analitiği sosyal medya read model'lerini tüketebilir fakat SM01/SM02'nin operasyonel sahibi değildir.
- Hiçbir paralel ekip ortak enum, event veya tabloyu onaysız değiştiremez; değişiklik karar kaydına döner.

## 9. Görsel ve kullanıcı kararı izlenebilirliği

| Görsel | Gereksinim | Modül |
|---|---|---|
| 145434 | Mailbox, ihale kaynağı, onay/hiyerarşi, belge iletişimi, proje aşaması, not/gecikme/sorun, cloud link | M02–M12, M21 |
| 145447 | Ayrı lojistik yeteneği; görevler workshop ile tamamlanacak | M15 |
| 145516 | Fiyat/fizibilite, teklif dokümanları, final üst onay, kritik proje görüşü, şartname/marka/sorumluluk/süre | M10 |
| 145533 | İhale tarama, toplantı/ziyaret, müşteri portföyü, lisans/yıllık takip, mailbox, MS Project | M08–M10, M21 |
| 145542 | TKLF/PRJ kodu, teklif arşiv/revizyon, firma 360, şartname özeti | M08–M12 |
| 145603 | Personel ihtiyacı, dış AI ilan/ihtiyaç desteği, gizli CV/ücret, performans | M02, M07 |
| 145613 | İş Alım kapanınca Proje Grubu devri | M11 |
| 145651 | Ürün/ofis talebi, katalog, stok, seri/görsel, teslim/lojistik, maliyet/onay | M13–M15 |
| 145703 | İşe girişin finansal adımı, gelir-gider, fatura, vergi/ödeme | M07, M14 |
| 145713 | Teknik ihtiyaç, elektrik/fiber/kamera/panel, arıza/change, test/commissioning | M16–M18 |
| 145722 | Test ekipmanı/dokümanı, aşama/kanıt, harici kontrol, standart rapor | M05–M06, M18 |
| 145733 | Saha deadline, prosedür/plan, personel handoff, günlük/dönemsel rapor, kabul teyidi | M05, M14, M17 |
| 145742 | Dış gecikme analizi, plan onayı, sözleşme Q&A, rol bazlı bildirim, çözüm önerisi | M06, M12, M17, M20 |

4 Eylül 2026 kullanıcı kararı; projeden bağımsız Sosyal Medya alanı, hesap/entegrasyon, içerik planı, istatistik, özel gün T−4/T−3 bildirimi ve ileride departmanlaşma gereksinimleri `SM01–SM02`, `PG-SM` ve `DB-GSM` ile izlenir.

## 10. Dalga kabul yöntemi

- Her dalga için örnek gerçek süreç, anonimleştirilmiş veri ve sorumlu iş sahibi belirlenir.
- DBA/DevOps gerekli şemayı dış süreçte uygular; Codex migration çalıştırmaz.
- Staging üzerinde manuel senaryo matrisi yürütülür.
- Yetki negatif senaryoları, duplicate/retry, sürüm değişmezliği ve audit kanıtı özellikle kontrol edilir.
- Açık kritik hata veya veri bütünlüğü riski varken sonraki dalga production'a alınmaz.
- Pint, Laravel/PHP test suite'i ve migration komutları kabul sürecinde dahi çalıştırılmaz.

## 11. Ertelenen konular (planın sonu)

Bu başlıklar kullanıcı kararıyla kapsam dışına alındı. Sıradaki modüllerin hiçbiri bunlara bağlı değildir; ihtiyaç doğduğunda ayrı yetkilendirmeyle ele alınır.

| # | Konu | Karar | Neden ertelendi | Geri geldiğinde ne gerekir |
|---|---|---|---|---|
| E-01 | **Sistem hesapları** (otomatik işleri yapan makine kimlikleri) | D-48, 5 Eylül 2026 | Bu işleri yazılımcı kod, job veya dış yapay zekâ bağlantısı ile yapacak; arayüzde yönetilen bir hesap listesine şimdilik gerek yok | `service_accounts` tablosu, model, yetkilendirme ve hareket kaydında hesap bağı yeniden tanımlanır |
| E-02 | **Giden kutusu** (olay mesajı üretimi ve hedef bazlı teslim takibi) | D-47, 5 Eylül 2026 | Böyle bir teslim mantığı kurulmayacak; olay geçmişinin tek kaynağı Personel Hareketleri'dir | Dış sisteme olay yayınlama ihtiyacı doğarsa yeniden tasarlanır |
| E-03 | **Ayrı durum değişikliği tablosu** | D-47, 5 Eylül 2026 | Durum geçişleri zaten Personel Hareketleri'nde okunur biçimde tutuluyor; ikinci bir geçmiş tablosu gereksiz | Rapor veya denetim ihtiyacı doğarsa Personel Hareketleri üzerinden view ile karşılanır |
| E-04 | **Bildirim arşivi ekranı** ("geçmiş bildirimler / tüm bildirimler") | D-49, 5 Eylül 2026 | Bildirimler Filament'in kendi bildirim zilinde gösterilir; menüden erişilen ayrı bir bildirim alanı istenmiyor | Zil panelinin içine "tüm bildirimler" düğmesi ve buradan açılan detaylı liste eklenir; menüde yer almaz |
| E-05 | **İki adımlı doğrulama** | D-50, 5 Eylül 2026 | Projede böyle bir doğrulama olmayacak; `mfa_enforced` kolonu, alanı ve etiketi tamamen kaldırıldı | Karar değişirse şema, giriş akışı ve kurtarma kodları birlikte yeniden tasarlanır |
| E-06 | **Dil ve saat dilimi seçimi** | D-51, 5 Eylül 2026 | Şu an tüm personel Türkiye'de; ekranı kalabalıklaştırıyor | Yabancı personel alındığında personel formuna geri eklenir; kolonlar şemada duruyor, varsayılan `tr` / `Europe/Istanbul` |
| E-07 | **Sicil no ve departman kodu gösterimi** | D-51, 5 Eylül 2026 | Kullanıcı bu iki alanı ekranda istemiyor | Kolonlar şemada duruyor. Departman kodu addan otomatik üretilir ve yalnız sistem içinde (seed, eşleşme) kullanılır |
| E-08 | **Dış sistem ve dış bağlantı ekranları** (`integration_endpoints`, `integration_connections`, `external_id_mappings`, `inbox_messages`, `sync_attempts`, `sync_errors`) | D-56, 5 Eylül 2026 | Entegrasyon arayüzde yönetilen bir özellik değil, yazılımcının işidir. Kullanıcı tek bir sistem kullandığını düşünecek; arka planda hangi projeye bağlanıldığını bilmeyecek | Kod içinde yapılır. Bağlantı bilgisi gerekirse `.env` veya `config` üzerinden okunur; ekran açılmaz |
| E-09 | **Paylaşım bağlantısında yetki / şifre** (`document_shares`) | D-75, 10 Eylül 2026 | Kullanıcı kararı: bağlantı şimdilik yetkisiz erişime açık olsun; yetki veya şifre mekanizması belgeler için sonra geliştirilecek | `document_shares`'a şifre hash'i / giriş zorunluluğu / izinli kişi listesi kolonları, paylaşım panelinde doğrulama adımı, erişim kaydı; mevcut açık bağlantılar geçiş kuralıyla kapatılır |
| E-10 | **Çalışanlar arası sohbet** (`conversations`, `messages`, üyelik, okundu imleci, bahsetme, ek, iş kaydı bağı — 08 §2.1–2.8, B12) | Kullanıcı isteği, 10 Eylül 2026 | Gelecek plana yazıldı; tasarım sözlükte hazır, uygulama ayrı yetkilendirme ister | B12 migration'ı (iletişim tabloları), `PostMessage` servisi, Filament sohbet ekranı (Filament-native; gerekirse case-specific onayla özel bileşen), bildirim zili bağı, `personnel_activities` `message.sent/.edited/.redacted` kayıtları |

Bildirim tablosu (`notifications`) bu erteleme kapsamında değildir: Filament'in kendi veritabanı bildirimleri için kalır ve menüde görünmez.

## 12. Sıradaki tasarım maddesi: Panolar (yönetici ve personel)

**Kullanıcı talebi (8 Eylül 2026):** Ana sayfada yöneticiye tekliflerin ve projelerin özet raporları (teklif hunisi, proje adım dağılımı, eksik beklentisi olan projeler, gecikmeler, açık issue/risk) gösterilecek; personel için ise departmana veya kişiye özel bir pano olacak — "bugün şu işleri yapmalısın", "yapılacaklar", "dünden kalanlar" gibi görev odaklı veriler ve personeli ilgilendiren raporlar (bazı raporların kontrolü personelde olabilir).

**Bağımlılık ve sıra:** Görev verisi B11 `tasks` / `project_tasks` (M06) ile, rapor özetleri M05 rapor motoruyla gelir; panolar bu ikisinden sonra ayrı yetkilendirmeyle tasarlanır. Odak beklentileri (`focus_expectations` + `ProjectStepReadiness`, D-68) departman panosunun "doldurulmayı bekleyen alanlar" listesini şimdiden besleyebilir.

| Pano | Hedef kitle | İçerik (ilk taslak) |
|---|---|---|
| Yönetici panosu | system_admin, yönetim | Teklif hunisi (aşama / adet / tutar), proje adım dağılımı, eksik beklentisi olan projeler, gecikmeler, açık risk ve issue'lar, bu ayın kilometre taşları |
| Departman panosu | Proje, Satın Alma, Muhasebe, Lojistik, Saha, Yazılım | Odağı bu departmanda olan projeler, doldurulmayı bekleyen alanlar, departmana atanmış görevler |
| Kişisel pano | Her personel | Bugünkü görevler, yapılacaklar, dünden kalanlar, sorumlusu olduğu proje kayıtları, kendisiyle ilgili raporlar |
