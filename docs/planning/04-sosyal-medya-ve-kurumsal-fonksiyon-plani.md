# Konelsis Kurumsal Platform — Sosyal medya ve dönüşebilir kurumsal fonksiyon planı

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Entegrasyon bağlantısı ve şema uygulaması ayrı yetkilendirme ister.  
**Sürüm:** 0.8 / 5 Eylül 2026

## 1. Amaç ve kapsam sınırı

Sosyal Medya, proje yaşam döngüsünün bir adımı ve mevcut bir departman değildir. Şirket genelinde çalışan, projeden bağımsız bir **kurumsal iş fonksiyonu** olarak tasarlanacaktır.

İlk kapsam:

- Resmî sosyal medya hesaplarının doğrulanmış basit envanteri.
- Hesap sorumluları, erişim durumu ve entegrasyon sağlığı.
- TR/EN içerik fikri, brief, üretim, inceleme, onay, planlama ve yayımlama takvimi.
- Görsel/video/doküman varlıklarının DMS revizyonlarına bağlanması.
- Özel gün kataloğu, yıllık gerçekleşmeleri ve T−4/T−3 bildirimleri.
- Manuel veya platform API'sinden alınan hesap/içerik istatistikleri.
- Filament-native operasyon ve yönetim ekranları.
- İleride Sosyal Medya departmanı kurulursa veriyi taşımadan organizasyon birimine bağlanma.

Proje, teklif veya müşteri kaydı olmadan sosyal medya işi oluşturulabilir. İlk sürümde sosyal medya kayıtlarında zorunlu `project_id` bulunmaz. Gelecekte kamuya açıklanması onaylanmış bir proje veya kurumsal kayıt içerik konusu yapılacaksa bu ilişki ayrı güvenlik ve yayın onayıyla eklenir; sosyal medya kaydının yaşam döngüsü projeye devredilmez.

Şirket bilgi tabanı Konelsis'in kamuya açık LinkedIn varlığını ve kurumsal görünürlük/sponsorluk örneğini gösterir. Bunlar kapsam girdisidir; mevcut hesaplar, yöneticiler veya metrikler yetkili şirket kullanıcısı doğrulamadan üretim ana verisi sayılmaz.

## 2. Departmandan bağımsız iş fonksiyonu modeli

Organizasyon yapısı ile iş kabiliyeti ayrı kavramlardır:

- `OrgUnit`: resmî departman/birim yapısıdır.
- `FunctionalArea`: kalıcı iş alanıdır; örneğin `social_media`.
- `FunctionalAreaResponsibility`: personel, ekip veya pozisyonun alandaki tarihçeli rolüdür.
- `FunctionalAreaOrgUnitBinding`: alanın bir veya daha fazla organizasyon birimiyle tarihçeli sahiplik/katılım bağıdır.

Sosyal Medya başlangıçta `functional_team` yönetişim modunda çalışır. Aktif üyeler `content_creator`, `reviewer`, `approver`, `publisher`, `analyst` ve `function_owner` rollerinden birini veya birkaçını alabilir.

İleride departmanlaşma şu kontrollü geçişle yapılır:

1. Yeni `OrgUnit` ve gerekli pozisyonlar oluşturulur.
2. Sosyal Medya fonksiyonu efektif tarihli `owner` bağıyla bu birime bağlanır.
3. Açık görev, onay, bildirim ve hesap sorumlulukları geçiş planıyla yeniden çözülür.
4. Geçiş öncesi owner/assignee snapshot'ları değiştirilmez.
5. Sosyal medya tabloları, kimlikleri, içerikleri ve metrikleri taşınmaz veya yeniden numaralandırılmaz.
6. Değişiklik append-only yönetişim olayı ve audit kaydı üretir.

Aynı model ileride Hukuk, Kurumsal İletişim veya benzeri yeni fonksiyonların departmanlaşmasını destekleyebilir. Bu ortak katman yalnız sahiplik/yetki sağlar; her iş alanının kendine özgü domain tabloları korunur ve her şey tek bir generic tabloya dönüştürülmez.

## 3. Roller ve görevler ayrılığı

| Rol | Sorumluluk | Varsayılan sınır |
|---|---|---|
| `function_owner` | Hesap envanteri, iş kapasitesi, eskalasyon ve politika sahibi | İçeriği tek başına hazırlayıp yayımlayamaz |
| `content_creator` | Fikir, brief, metin ve varlık hazırlığı | Final onay/yayın yetkisi yok |
| `reviewer` | Dil, marka, doğruluk, hak ve uygunluk incelemesi | Yayın yetkisi ayrı capability'dir |
| `approver` | Belirlenen risk matrisine göre final karar | Kendi değişikliğinde maker-checker uygulanır |
| `publisher` | Onaylı exact sürümü manuel/API ile yayımlar | Onaysız veya farklı sürüm yayımlayamaz |
| `analyst` | Metrik toplama ve performans raporu | Credential ve içerik değiştirme yetkisi yok |

Yüksek riskli içerikler için hukuk/yönetim/İK veya proje sahibi gibi ek onaycılar policy sürümüyle çözülür. “Yönetici” olmak sosyal hesap credential'ına veya taslak içeriğe otomatik erişim vermez.

## 4. Sosyal hesap envanteri

Her hesap için en az şu bilgiler tutulur:

- Platform, görünen ad, kullanıcı adı/handle ve doğrulanmış profil URL'si.
- Harici platform hesap kimliği mevcutsa kimlik ve doğrulama zamanı.
- Hesap durumu: taslak, doğrulanıyor, aktif, askıda, devrediliyor, kapalı.
- Varsayılan dil, hedef kitle, ülke/saat dilimi ve içerik sorumlusu.
- Kurumsal sahiplik kanıtı ve son gözden geçirme tarihi.
- Entegrasyon durumu, izin kapsamı, token sona erme zamanı ve son başarılı senkronizasyon.
- Hesap açılış/kapanış/devir geçmişi.

Şifre, access token, refresh token, recovery code veya platform secret'ı uygulama veritabanında ya da DMS'de tutulmaz. Yalnız merkezi secret manager içindeki kayda ait erişim vermeyen `credential_reference` saklanır.

Hesap kullanıcı adı değişiklikleri geçmiş kimlik tablosunda tutulur. Aynı platform ve doğrulanmış external account ID ikinci aktif hesaba bağlanamaz.

## 5. İçerik üretim ve yayın planı

### 5.1 İçerik türleri

İlk katalog:

- Kurumsal duyuru ve başarı.
- Proje/referans paylaşımı; yalnız kamuya açıklama onayı varsa.
- Teknik bilgi, enerji sektörü içeriği ve etkinlik.
- İşveren markası ve işe alım; İK onayıyla.
- Sponsorluk, sosyal sorumluluk ve toplum içeriği.
- Özel gün ve anma içeriği.
- Acil düzeltme/kriz iletişimi; ayrı hızlandırılmış onay politikasıyla.

### 5.2 İçerik kaydı

Her içerik kaydı şunları taşır:

- İçerik türü, amaç, hedef kitle, owner ve öncelik.
- Kampanya veya özel gün bağlantısı.
- Hedef sosyal hesaplar ve her hesap için planlanan yayın zamanı.
- TR/EN başlık, metin, alternatif metin ve gerekli link/etiket bilgileri.
- DMS'deki exact görsel/video/doküman revizyonları.
- Marka, hukuk, gizlilik, telif/izin ve kamuya açıklama checklist'i.
- Onaylanan exact içerik sürümünün hash'i.
- Gerçek yayın zamanı, harici paylaşım ID/URL'si ve manuel yayın kanıtı.

İçerik ve kanal ayrılır: tek ana içerik birden fazla hesaba hedeflenebilir fakat her kanalın metin, görsel oranı, dil, yayın zamanı, onay ve yayın sonucu ayrı tutulur.

### 5.3 Durum makinesi

```text
content item: idea → planned → in_production → completed → archived
              idea/planned/in_production → cancelled

content version: draft → submitted → changes_requested → draft
                 submitted → approval_pending → approved | rejected
                 approved → superseded

publication target: planned → scheduled → queued → publishing → published
                    publishing → failed → retrying → publishing
                    failed → manual_action_required → published | cancelled
                    planned/scheduled/queued → cancelled
```

Onaylanan sürüm yerinde değiştirilemez. Metin, hedef hesap, saat veya varlık değişirse yeni sürüm oluşur ve etki matrisine göre yeniden onay gerekir. `published` kayıt hard delete edilmez; yanlış paylaşım kaldırılırsa platformdaki kaldırma olayı ve gerekçesi ayrıca kaydedilir.

## 6. Özel gün ve kritik zaman sistemi

Özel gün iki katmanlıdır:

- `SpecialDayDefinition`: değişmeyen kavram, kategori, TR/EN ad, ülke/bölge, öncelik ve kaynak.
- `SpecialDayOccurrence`: ilgili yıldaki kesin yerel tarih/saat, timezone, doğrulama durumu ve kaynak snapshot'ı.

Yıllık tekrar kuralı yalnız güvenilir olduğu günlerde otomatik occurrence üretir. Tarihi hareketli veya resmî karara bağlı günler her yıl yetkili kişi tarafından doğrulanmadan yayıma hazır kabul edilmez.

Varsayılan bildirim politikası:

- **T−4:** İlgili occurrence'dan dört gün önce yapılandırılmış yerel saatte Sosyal Medya `content_creator` ve `function_owner` rollerine ilk hatırlatma.
- **T−3:** Sorumlu atanmış ve içerik en az `drafting` hazırlık seviyesine gelmiş değilse üç gün önce kritik uyarı; `function_owner` ve tanımlı üst eskalasyon alıcısına gönderim. Yetkili `not_applicable` kararı da uyarıyı kapatabilir.
- **T−1 opsiyonel:** Onaylı/scheduled içerik yoksa yalnız kuralda açılmışsa son durum uyarısı.
- Özel gün sisteme T−4'ten daha geç girilirse bir sonraki taramada gecikmiş tek kritik bildirim oluşturulur.

Her occurrence + rule version + recipient için tek idempotency anahtarı kullanılır. Okundu bilgisi ile “sorumluluğu aldım” acknowledgment'ı ayrıdır. Resmî tatil/hafta sonu davranışı kural bazında `calendar_day` veya `business_day` seçimiyle belirlenir.

Özel gün kategorileri başlangıçta ulusal/resmî, enerji/çevre/sektör, kurumsal yıldönümü, sponsorluk/topluluk ve İK/işveren markası olarak ayrılır. Nihai katalog Sosyal Medya iş sahibi ve yönetim tarafından onaylanır.

## 7. Entegrasyon stratejisi

Entegrasyon “her platformda otomatik yayın var” varsayımıyla tasarlanmaz. Her hesap için kabiliyet matrisi tutulur:

- Hesap/profil doğrulama.
- Hesap metriklerini okuma.
- İçerik metriklerini okuma.
- İçerik yayımlama/zamanlama.
- Webhook/olay alma.
- Medya yükleme ve platform sınırlamaları.

Uygulama sırası:

1. Manuel hesap envanteri ve manuel yayın kanıtı.
2. Platformun resmî API'si, hesap türü, sözleşme/şartlar, izin scope'ları ve oran limitleri için fizibilite.
3. Salt okunur profil/metrik bağlantısı.
4. Güvenlik ve yayın onayı geçerse onaylanan exact sürüm için otomatik publish.
5. Webhook ve periyodik reconciliation ile dış platform gerçeğini doğrulama.

Her platform ayrı infrastructure adapter'ıdır. Filament Resource/Page dış API çağırmaz. Publish isteği application service tarafından yetkilendirilip kuyruk üzerinden idempotent gönderilir. Timeout, rate limit veya API kesintisi içeriği “yayımlandı” saymaz; doğrulanmış platform ID/URL veya yetkili manuel kanıt gerekir.

Plugin kurulumu bu planın parçası değildir. Platform API paketi veya Filament takvim plugin'i ancak adı, lisansı, sürüm uyumu ve bakım durumu incelenip kullanıcı tarafından ayrıca onaylanır.

## 8. İstatistik ve KPI modeli

İstatistikler iki kaynaktan gelebilir:

- Resmî platform API'sinden alınan doğrulanmış snapshot.
- API mümkün değilse yetkili kullanıcının kaynak tarihi ve kanıtla girdiği manuel snapshot.

İlk normalize metrik kataloğu:

- Takipçi/abone toplamı ve net büyüme.
- Erişim ve gösterim.
- Etkileşim toplamı ve tanımlı etkileşim oranı.
- Link tıklaması.
- Video görüntüleme; platform tanımıyla birlikte.
- İçerik adedi, planlanan/yayımlanan oranı ve zamanında yayın oranı.
- Hesap bağlantı/senkronizasyon sağlığı.
- Özel gün hazırlık ve zamanında onay oranı.

Metrik adı aynı olsa bile platform tanımları eşdeğer kabul edilmez. Her fact; platform, hesap/içerik, metric definition version, period, timezone, source, fetched/entered time ve kaynak güven düzeyini taşır. Türetilmiş KPI formülü versiyonlanır; önceki dashboard dönemleri sessizce yeniden hesaplanmaz.

## 9. Filament-native arayüz

Sosyal Medya `/app` panelinde ayrı Navigation Group/Cluster olarak görünür; departmanlaşma ayrı panel açmayı gerektirmez.

| Ekran | Filament-native yaklaşım |
|---|---|
| Hesaplar | Resource/Table, badge, filtre, Infolist, bağlantıyı doğrula Action'ı |
| İçerik planı | Tarih aralığı filtreli Table, Tabs, grouping ve durum badge'leri |
| İçerik hazırlama | Form Sections/Tabs/Wizard, RichEditor, FileUpload yerine DMS revision seçimi |
| İnceleme/onay | Infolist + Revizyona Gönder/Onayla Action'ları |
| Yayın kuyruğu | Table + Yayınla/Yeniden Dene/Manuel Yayın Kaydet Action'ları |
| Özel günler | Resource/Table + occurrence üret/doğrula Action'ları |
| Hazırlık kutusu | Table Widget: T−4/T−3, eksik owner/içerik/onay |
| İstatistik | StatsOverview, Chart ve Table Widgets; Query Service/read model |

İlk sürümde özel JavaScript takvim, drag-and-drop veya özel Blade/HTML/CSS yazılmaz. Filament Table tarih filtreleri yeterli kabul edilir. Daha sonra görsel sürükle-bırak takvim istenirse native sınır analizi ve özellik bazlı kullanıcı onayı gerekir.

## 10. Katmanlı servis sınırları

Örnek application use-case'leri:

- `RegisterSocialAccount`
- `AssignBusinessFunctionRole`
- `CreateSocialContent`
- `SubmitSocialContentForReview`
- `ApproveSocialContentVersion`
- `ScheduleSocialPublication`
- `RecordManualSocialPublication`
- `RequestSocialPublication`
- `GenerateSpecialDayOccurrences`
- `DispatchSpecialDayReminder`
- `SyncSocialMetrics`
- `BindBusinessFunctionToOrgUnit`

Tablo ve dashboard sorguları `SocialContentCalendarQuery`, `SocialAccountHealthQuery`, `SpecialDayReadinessQuery` ve `SocialPerformanceQuery` gibi Query Service'lerde bulunur. External API, secret manager ve webhook işlemleri Infrastructure adapter katmanındadır.

## 11. Güvenlik, uygunluk ve audit

- Her hesap, içerik, metrik, özel gün ve Action için Policy/capability kontrolü.
- Credential yalnız secret manager'da; log, bildirim, DMS ve audit payload'ında secret yok.
- API scope'ları least-privilege; salt okunur ve publish yetkileri ayrı bağlantı/izin olarak izlenir.
- İçerikte personel, müşteri, proje, lokasyon ve kritik altyapı bilgilerinin kamuya açıklama kontrolü.
- Görsel/video için telif, kişisel veri, çalışan/müşteri rızası ve kullanım hakkı kaydı.
- Onaylanan exact sürüm/hash ile yayımlanan payload eşliği.
- Manuel yayın, silme/düzeltme ve hesap devir işlemlerinde actor, zaman, neden ve kanıt.
- Webhook signature/replay koruması, idempotency ve reconciliation.
- Platform raw payload'ı minimum süreyle ve belirlenmiş retention politikasıyla tutulur.

AI ile metin/görsel önerisi ileride istenirse bu Laravel projesinde AI geliştirilmez. Yalnız açıkça onaylanan Filament Action, application service ve ayrı AI projesi API adapter'ı eklenebilir; sonuç taslak olur ve normal insan onay/yayın akışını atlayamaz.

## 12. Aşamalı teslim planı

### SM-P0 — Karar ve envanter kapısı

- Resmî platform/hesap listesi, sahiplik ve mevcut yöneticiler doğrulanır.
- Rol/RACI, onay matrisi, içerik kategorisi, özel gün kataloğu ve KPI sözlüğü onaylanır.
- Her platform için API fizibilitesi ve credential sahibi belirlenir.

### SM-P1 — Manuel operasyon çekirdeği

- Functional Area sahipliği ve tarihçeli personel rolleri.
- Hesap envanteri, içerik sürümü, DMS varlık bağı, onay ve manuel yayın kanıtı.
- Özel gün occurrence'ları, T−4/T−3 bildirimleri ve hazırlık dashboard'u.

### SM-P2 — Planlama ve yönetişim

- Kampanya/içerik takvimi, çoklu hesap hedefi, TR/EN varyantları.
- Maker-checker, hızlandırılmış kriz politikası, iptal/düzeltme ve audit.
- Manuel metrik snapshot'ları ve ilk dashboard.

### SM-P3 — Hesap entegrasyonları

- Onaylanan hesaplarda önce read-only profil/metrik adapter'ı.
- Ayrı güvenlik onayıyla publish adapter'ı, webhook ve reconciliation.
- Rate limit, retry, token expiry ve integration health operasyonu.

### SM-P4 — Olgun analitik

- Platformlar arası tanım farklarını koruyan trendler.
- İçerik türü, dil, saat ve kampanya karşılaştırması.
- KPI formül sürümleri ve yönetim read model'leri.

Modül planındaki `SM01`, SM-P0–SM-P2'yi; `SM02`, SM-P3–SM-P4'ü kapsar. SM01 ortak M01–M06 çekirdeği tamamlandıktan sonra İş Alım ve Operasyon modüllerinden bağımsız geliştirilebilir. SM02'nin her platform/hesap bağlantısı ayrı entegrasyon kabul kapısından geçer.

## 13. Kabul senaryoları

- Sosyal Medya departmanı olmadan yetkili bir functional team içerik üretebilir ve onay akışını tamamlayabilir.
- Fonksiyon ileride yeni Sosyal Medya departmanına bağlandığında eski içerik, metrik ve owner snapshot'ları değişmez.
- Dört gün kala ilk bildirim yalnız bir kez oluşur; üç gün kala hâlâ plan/acknowledgment yoksa doğru owner'a kritik eskalasyon gider.
- Pasif kullanıcı veya boş rol nedeniyle bildirim kaybolmaz; unresolved recipient kaydı operasyon kutusuna düşer.
- Onaydan sonra metin veya medya değişirse önceki onayla yayımlanamaz.
- Aynı idempotency key ile tekrarlanan publish isteği ikinci paylaşım oluşturmaz.
- API timeout'unda kayıt published olmaz; reconciliation veya manuel kanıt bekler.
- Credential hiçbir kullanıcı ekranı, export, log veya bildirimde görünmez.
- API olmadan manuel hesap, yayın ve metrik akışı çalışır.
- TR ve EN içerik varyantları ayrı sürüm ve onayla izlenir.
- Yetkisiz kullanıcı sosyal hesap bağlantısını, taslağı veya analitiği göremez.

## 14. Uygulama öncesi açık kararlar

1. Resmî platform ve hesap listesi; her hesabın doğrulanmış URL/external ID'si.
2. Mevcut hesap sahibi, admin'ler ve secret manager sorumlusu.
3. İlk functional team üyeleri ve rol/RACI matrisi.
4. İçerik türleri, marka dili, yasak/kısıtlı konu ve kamuya açıklama politikası.
5. Normal, yüksek riskli ve kriz içeriklerinin onay rotaları/SLA'ları.
6. TR/EN üretim zorunluluğunun hesap ve içerik türüne göre matrisi.
7. Özel gün ana kataloğu, ülke/bölge kapsamı, kaynak ve T−4/T−3 gönderim saatleri.
8. Hafta sonu/tatil için calendar-day veya business-day davranışı.
9. İlk KPI sözlüğü, platform metrik tanımları ve veri saklama süreleri.
10. Hangi platformlarda yalnız metrik okuma, hangilerinde publish beklendiği.
11. Platform uygulaması/API sözleşmesi, ücret, rate limit, izin ve webhook şartları.
12. DMS sosyal medya varlık türleri, telif/rıza kanıtı ve retention.
13. İleride departmanlaşma kararını verecek makam ve efektif geçiş prosedürü.

Bu kararlar, DB-GSM ve plan paketi onayı tamamlanmadan sosyal medya migration'ı veya uygulama kodu hazırlanmaz. Codex migration, Laravel/PHP test paketi veya Pint çalıştırmaz.
