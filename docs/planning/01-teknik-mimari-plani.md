# Konelsis Kurumsal Platform — Teknik mimari planı

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Paket kurulumu ve şema komutu çalıştırma kapalıdır.  
**Sürüm:** 0.8 / 5 Eylül 2026

## 1. Bağlayıcı teknik kararlar

- Mevcut taban Laravel 13, Filament 5 ve Livewire 4'tür.
- Hedef mimari mikroservis değil, iş alanları ayrılmış modüler Laravel monolitidir.
- Functional Area, OrgUnit ve Project ayrı domain kimlikleridir; departmanlaşabilecek bağımsız alanlar doğrudan departman veya proje FK'sına kilitlenmez.
- Üretim hedefi MySQL 8.4 LTS/InnoDB + Redis + private S3 uyumlu object storage'dır; mevcut SQLite yalnız geliştirme iskeletidir.
- Öncelik Personel/Organizasyon → Genel Raporlama → Bildirim/Escalation → DMS/İletişim → İş Alım → Operasyondur.
- Filament Resource ve Page sınıfları ince/deklaratif kalır; iş kuralı ve sorgu mantığı servis katmanlarına gider.
- Uygulama ekranları Filament-native bileşenlerle kurulur.
- Rapor/doküman PDF çıktısına özel Blade/HTML kullanımına kullanıcı onay vermiştir. Bu onay panel Blade'i, genel HTML, CSS, JavaScript veya tema değişikliğini kapsamaz. Print CSS gerekirse uygulamadan hemen önce ayrıca onay alınır.
- AI uygulaması bu repository'de bulunmaz. Yalnız ayrı AI projesi için özellik bazlı button/Action ve API istemci adaptörü bulunabilir.
- Plugin ve Composer bağımlılığı, adı ve amacı belirtilerek ayrıca onaylanmadan kurulmaz.
- Pint, Laravel/PHP test paketleri, bütün Artisan migration komutları ve DB reset/refresh/wipe/flush/drop/truncate işlemleri hiçbir zaman çalıştırılmaz.

## 2. Mevcut taban ve ilk güvenlik işi

Mevcut uygulama henüz iş alanı modeli içermeyen Laravel/Filament iskeletidir:

- Tek `/admin` paneli ve giriş ekranı.
- `User` modeli ve Laravel'in personnel/cache/jobs tablolarına ait başlangıç migration dosyaları.
- TR/EN locale middleware'i ve `TR / EN` dil seçici.
- SQLite ile database session/cache/queue ve local dosya sistemi.
- `composer.json` içinde migration ve Laravel testini dolaylı çalıştırabilen scriptler; geliştirme başlamadan güvenli hâle getirilmeleri gerekir.

Planlama kapıları kapandıktan sonraki ilk uygulama teslimi, yanlışlıkla yasak komut çalıştırma riskini kaldırmalı ve geliştirme/staging/production ortamlarını ayırmalıdır. Bu aşamada bile migration çalıştırılmaz.

## 3. Hedef topoloji

```text
Kullanıcı
   ↓
Filament /app veya /admin
   ↓
Application Use Case + Policy
   ↓
Domain Model + Transaction + Outbox
   ├── MySQL 8.4 LTS / InnoDB
   ├── Redis Queue/Cache/Session/Lock
   ├── Private Object Storage
   └── Infrastructure Adapters
          ├── E-posta
          ├── PDF renderer
          ├── Zirve / diğer kurumsal sistemler
          └── Ayrı AI projesi API'si
```

Dağıtım birimleri:

- Stateless web uygulaması.
- Ayrı queue worker'ları: bildirim, belge/PDF, entegrasyon ve harici AI.
- Ayrı scheduler süreci: rapor dönemleri, hatırlatmalar, overdue ve escalation.
- MySQL, Redis ve object storage private ağda.
- Merkezi secret manager, log, metric, alarm ve yedekleme.

MySQL fiziksel veri sözleşmesi:

- Bütün operasyonel tablolar `InnoDB`, karakter verileri `utf8mb4` kullanır; kesin collation Türkçe/İngilizce sıralama ve benzersizlik örnekleriyle DB-G7'de dondurulur.
- Teknik sayısal kimlik anahtarları ve bütün karşılık gelen foreign key'ler `BIGINT UNSIGNED` tutulur; uygulama katmanı kimlikleri doğrudan sayısal olarak kullanır.
- İşlem zamanları UTC `DATETIME(6)`; yerel iş tarihi ayrıca `DATE` ve IANA timezone kimliğiyle modellenir.
- Esnek snapshot/konfigürasyon alanlarında native `JSON` kullanılır; indekslenecek JSON path'leri generated column'a çıkarılarak normal indekslenir.
- PostgreSQL'e özgü array, partial index, exclusion constraint, materialized view, `timestamptz`, `jsonb` ve PostGIS varsayımları kullanılmaz.
- Tarih aralığı çakışmaları ve tek-aktif-kayıt kuralları servis transaction'ı, kilit satırı ve gerektiğinde nullable generated-column unique index ile korunur.

## 4. Uygulama katmanları

```text
Filament Resource / Page / Widget / Action
                    ↓
          Input DTO + Application Use Case
                    ↓
          Domain Model / Enum / Policy
                    ↓
       Query Service / Infrastructure Port
                    ↓
      Database / Queue / Storage / API Adapter
```

### Filament UI katmanı

- Form, Table, Infolist, Widget, Cluster, Notification ve Action tanımlar.
- Girdiyi doğrular, Policy kontrolü yapar, uygulama servisini çağırır ve sonucu gösterir.
- Raw SQL, açık Eloquent/query-builder zinciri, transaction, e-posta, PDF veya dış API çağrısı içermez.

### Application katmanı

- Tek amaçlı use-case'ler, DTO'lar, transaction sınırı, idempotency ve hata eşlemesini içerir.
- Örnek davranışlar: çalışan atama, rapor şablonu yayımlama, rapor dönemi üretme, rapor gönderme, revizyona iade, kritik iş oluşturma, PDF talebi, harici rapor kontrolü.

### Query katmanı

- Filament tabloları, filtreleri, dashboard, KPI, rapor/export ve kompleks Eloquent/SQL sorguları burada çalışır.
- Kart ve tablo görünümleri aynı read model/query service üzerinden beslenir.

### Domain katmanı

- Modeller, ilişkiler, enumlar, Policy'ler, değişmezler ve domain event'leri.
- Durum kolonları formdan doğrudan düzenlenmez; transition use-case'i ile değişir.

### Infrastructure katmanı

- E-posta, dosya, PDF, queue, Zirve ve ayrı AI projesi adaptörleri.
- Her dış sistem port/adapter arkasındadır; Filament dosyası dış servise doğrudan bağlanmaz.

## 5. Modüler sınırlar

İlk dalga bounded context'leri:

- Identity & Access
- Organization & Personnel
- Functional Area Governance
- Reporting
- Notification & Escalation
- Document Control
- Collaboration
- External Service Gateway
- Audit & Workflow

İkinci dalga:

- Party & CRM Master Data
- Business Development
- Tender & Proposal
- Contract & Commercial
- Project Orchestration

Üçüncü dalga:

- Procurement
- Logistics & Inventory
- Project Finance Control
- Engineering & Automation
- Field Operations
- Quality/HSE
- Testing & Commissioning
- Warranty & Service

Projeden bağımsız paralel bounded context:

- Social Media & Corporate Communications

Sosyal Medya, ortak Identity/Organization/Functional Area, DMS, Workflow, Notification ve External Service Gateway sözleşmelerini kullanır; Party/Project aggregate'lerinin alt modülü değildir.

## 6. Panel ve Filament yaklaşımı

- `/admin`: sistem, kullanıcı, rol/yetki, şablon, entegrasyon ve ana veri yönetimi.
- `/app`: bütün personelin günlük operasyon paneli.
- `/field`: yalnız saha ihtiyacı olgunlaştığında responsive sade panel; ilk aşama online'dır.

Departman başına ayrı panel açılmaz; süreçler departmanlar arasıdır. Navigation Groups/Clusters ile alanlar ayrılır. Sosyal Medya `/app` içinde bağımsız bir Cluster/Navigation Group olur; ileride departmanlaşması panel veya route değişikliğini zorunlu kılmaz.

### Native UI eşlemesi

| İhtiyaç | Filament yaklaşımı |
|---|---|
| Personel kartları | Table `contentGrid`, ImageColumn, Stack, badge, filtre |
| Personel tablosu | Server-side Table, search, filter, sort |
| Profil detayları | Infolist Sections/Tabs ve Relation Managers |
| Organizasyon/hiyerarşi | Filtrelenebilir hiyerarşik tablo; ilk sürümde özel grafik yok |
| Rapor doldurma | Forms, Sections/Tabs/Wizard, Repeater/Builder, FileUpload |
| Rapor inceleme | Infolist + Onayla/Revizyona Gönder Action'ları |
| Bildirim | Filament database notifications + ilk sürüm polling |
| Kritik iş | Action modalı + görev/bildirim/acknowledgment |
| Mesaj dizisi | Resource/Relation Manager + Action modalı |
| Sosyal hesaplar | Resource/Table + Infolist + bağlantıyı doğrula/yenile Action'ları |
| Sosyal içerik planı | Tarih filtreli/grouped Table, Tabs, `contentGrid` ve durum badge'leri |
| İçerik inceleme/yayın | Infolist + Revizyona Gönder/Onayla/Planla/Yayınla Action'ları |
| Özel gün hazırlığı | Resource/Table + T−4/T−3 durumlarını gösteren Table Widget |
| Sosyal medya analitiği | StatsOverview, Chart ve Table Widgets + Query Service/read model |
| Proje tablo/kart | Aynı Query Service ile Table ve `contentGrid` görünümü |
| Proje kapak görseli | FileUpload image + ImageColumn/ImageEntry |
| Stage/handoff | Relation Manager/Table + geçiş Action'ları |
| CSV/XLSX | Filament ExportAction; yetki ve formula-injection koruması |

Modern gerçek zamanlı chat, Gantt/Kanban veya sosyal medya takvimi sürükle-bırak, harita üzerinde KMZ, browser içi Word ortak düzenleme, gelişmiş PDF viewer veya offline PWA native sınırı aşarsa plugin/custom frontend karar kapısına gider. Sosyal medya ilk sürümünde özel JavaScript/Blade takvim yapılmaz; Filament Table tarih filtreleri kullanılır.

## 7. Personel, organizasyon ve yetki

### Kimlik ayrımı

- `User` giriş ve yetkilendirme hesabıdır.
- `Personel` personel ana kaydıdır.
- Bir çalışan geçici olarak hesapsız olabilir; bir kullanıcı en fazla bir aktif çalışanla bağlıdır.
- Personel numarası, kullanıcı ID'si ve harici bordro/muhasebe kimliği birbirinden ayrıdır.

### Tarihçeli yapı

- Departman/birim, pozisyon, lokasyon, doğrudan amir ve istihdam ataması `valid_from / valid_to` ile tutulur.
- Organizasyon ağacında ve manager zincirinde döngü oluşması application service tarafından engellenir.
- Fonksiyon yöneticisi, doğrudan amir, proje yöneticisi ve onaycı ayrı atanır.
- Vekâlet süreli, kapsamlı ve audit'li olur.

### Yetkilendirme

- Global RBAC + kayıt kapsamlı ABAC/Policy birlikte kullanılır.
- Kapsamlar: organizasyon birimi, proje rolü, veri sınıfı, sahiplik ve işlem türü.
- Yönetici olmak özel mesaj, maaş, sağlık, disiplin, aday/CV veya geçmiş ast verisine otomatik erişim sağlamaz.
- Filament strict authorization, Model Policy ve Action authorization zorunludur.
- Başlangıç rol seti: System Admin, HR Admin, Unit Manager, Personel, Report Coordinator, Executive, Auditor.
- Daha önce onaylanan Filament Shield ve Spatie Activitylog ancak uygulama aşamasında sürüm/şema incelemesi yapıldıktan sonra kurulur; proje kapsamı ve kritik geçmişler yine custom Policy/domain tablolarında kalır.

## 8. Genel raporlama teknik tasarımı

### Şablon motoru

Desteklenen ilk alan tipleri:

- Kısa/uzun metin ve Filament RichEditor.
- Sayı, para, yüzde ve ölçü.
- Tarih/saat.
- Select, radio, checkbox/toggle ve checklist.
- Personel, departman, proje veya ana veri referansı.
- Dosya/fotoğraf/kanıt.
- Tekrarlanan satır grubu.
- Salt okunur açıklama/callout.

Şablon editörü yalnız izinli native bileşenlerden seçim yaptırır. Kullanıcı tarafından çalıştırılabilir HTML/JavaScript alınmaz.

### Zaman ve atama

- Schedule; günlük, haftalık, aylık, özel cron-benzeri kurum kuralı veya olay bazlı olabilir.
- Assignment; çalışan, pozisyon, departman, ekip veya ileride proje rolüne verilir.
- Scheduler her dönem için ayrı report occurrence üretir; gönderim olmasa bile eksik/gecikmiş rapor görülebilir.
- Duplicate üretim, `assignment + period` idempotency anahtarıyla engellenir.
- Saatler UTC saklanır; deadline hesapları ilgili iş takvimi ve varsayılan `Europe/Istanbul` ile yapılır.

### Yaşam döngüsü

```text
Scheduled → Open → Draft → ReadyForCheck
          → ExternalCheckPending (şablonda gerekliyse)
          → Submitted → UnderReview
          → Approved | RevisionRequired
          → Archived
```

`Overdue`, `DueSoon` ve `Escalated` içerik durumundan ayrı zaman/uyarı durumlarıdır.

Gönderilen rapor yerinde değiştirilemez. Düzeltme yeni submission revision üretir; önceki sürüm ve karar geçmişi korunur.

### KPI ve sorgulama

- Dinamik şablon tanımı versiyonlu şema olarak saklanabilir.
- Kullanıcı yanıtları tipli response kayıtlarında tutulur; yalnız sunum konfigürasyonu MySQL native `JSON` olabilir. Sorgulanacak JSON değerleri generated column üzerinden indekslenir.
- KPI işaretli alanlar dönemsel metric fact tablosuna projekte edilir.
- Dashboardlar normalize işlem tablolarına ağır sorgu göndermek yerine Query Service/read model kullanır.

## 9. Bildirim, kritik iş ve escalation

İlk olay kataloğu:

- Personel/birim/yönetici ataması değişti.
- Rapor dönemi açıldı, teslim zamanı yaklaştı veya gecikti.
- Rapor gönderildi, onaylandı veya revizyona döndü.
- Kritik iş oluşturuldu, acknowledgment gecikti veya çözüldü.
- Doküman/PDF hazırlandı ya da başarısız oldu.
- Harici kontrol tamamlandı, reddedildi veya zaman aşımına uğradı.
- Özel gün için T−4 hazırlık hatırlatması veya T−3 kritik eskalasyon zamanı geldi.
- Sosyal içerik inceleme/onay SLA'sı yaklaştı veya geçti.
- Sosyal yayın başarısız oldu, hesap bağlantısı sona erdi/iptal edildi veya metrik senkronizasyonu gecikti.

Kural girdileri:

`olay + önem + organizasyon/proje/functional-area kapsamı + alıcı rolü + kanal + SLA/escalation politikası`

Teknik davranış:

- In-app Filament notification başlangıç kanalıdır; e-posta asenkron kanal olabilir.
- Bildirim gövdesinde hassas veri yerine yetkili kayda link verilir.
- Kritik bildirim acknowledgment ve gerekirse resolution ister.
- Aynı olay correlation/dedup key ile bildirim fırtınası oluşturmaz.
- Boş pozisyon veya pasif kullanıcı nedeniyle alıcı çözülemezse kayıt kaybolmaz; operasyon dashboard'una düşer.
- Üstten alta bildirim scope üzerinden, alttan üste bildirim etkin manager/escalation zincirinden çözülür.
- Sosyal medya bildirim alıcısı mevcut departman adına göre değil, olay anındaki Functional Area rolü üzerinden çözülür; çözülen alıcı snapshot'ı sonradan departmanlaşmayla değişmez.

## 10. Kayıtlı iletişim

İlk sürüm modern Slack benzeri chat değildir; Filament-native, iş nesnesine bağlı konuşma dizisidir:

- Şirket, departman, proje, grup ve bire bir konuşma türleri.
- Üye, mesaj, cevap zinciri, mention, düzenleme/redaction geçmişi ve okundu bilgisi.
- DMS dokümanı ve sanitize edilmiş link paylaşımı.
- Mesajdan görev, karar veya kritik iş üretildiğinde kaynak mesaj bağlantısı.
- Yönetici hiyerarşisi özel konuşmayı otomatik görünür yapmaz.

Typing/presence, reaksiyon, anlık sonsuz akış ve floating chat paneli istenirse ayrıca plugin veya custom frontend onayı gerekir.

## 11. Sosyal medya teknik tasarımı

Sosyal Medya bounded context'i şu katmanlara ayrılır:

- **Filament UI:** hesap, içerik, özel gün, yayın ve metrik Resource/Page/Widget'ları; yalnız input, authorize, use-case çağrısı ve sonuç sunumu.
- **Application:** içerik sürümü/onayı, yayın planlama, manuel yayın kanıtı, özel gün occurrence/uyarı üretimi, hesap senkronizasyonu ve departman bağlama use-case'leri.
- **Query:** içerik takvimi, hesap sağlığı, özel gün hazırlığı ve performans read model'leri.
- **Domain:** Functional Area sorumlulukları, sosyal hesap, immutable içerik sürümü, hedef/yayın, özel gün ve metric definition/fact modelleri.
- **Infrastructure:** platform API adapter'ları, erişim bilgisi kaydı, kuyruk, webhook doğrulama ve reconciliation.

İlk servis sözleşmeleri:

- `RegisterSocialAccount`
- `CreateSocialContent`
- `SubmitSocialContentForReview`
- `ApproveSocialContentVersion`
- `ScheduleSocialPublication`
- `RequestSocialPublication`
- `RecordManualSocialPublication`
- `GenerateSpecialDayOccurrences`
- `DispatchSpecialDayReminder`
- `SyncSocialMetrics`
- `BindFunctionalAreaToOrgUnit`

Her platform/hesap için `profile.read`, `metrics.read`, `content.publish`, `schedule.publish` ve `webhook.receive` kabiliyet matrisi ayrı tutulur. Baseline manuel hesap/yayın/metrik akışıdır. Platform API fizibilitesi doğrulanmadan otomatik yayın taahhüt edilmez; önce read-only bağlantı, sonra ayrıca onaylanan publish scope'u açılır.

Parola, OAuth access/refresh token, recovery code ve client secret DB/DMS/log içinde tutulmaz. Uygulama yalnız secret manager referansı ve güvenli bağlantı metadata'sı taşır. Publish exact onaylı sürüm/hash'i kuyruk üzerinden idempotent gönderir; harici post ID/URL veya yetkili manuel kanıt bulunmadan `published` durumu verilmez.

Doğrudan mesaj, yorum yönetimi, audience profiling, reklam bütçesi/harcaması ve otomatik moderasyon ilk kapsam dışında kalır. KVKK, platform şartları ve ayrı kapsam kararı olmadan eklenmez.

## 12. DMS ve dosya güvenliği

Kontrollü doküman ile sıradan ek ayrılır:

- Kontrollü doküman: numara, tip/disiplin, revizyon, durum, dil, inceleme/onay, dağıtım, transmittal ve retention.
- Ek: mesaj, rapor, görev veya aktiviteye yardımcı dosya; yine ACL ve audit'e tabidir.

Dosya binary'si object storage'da, metadata MySQL'de tutulur. Zorunlu kontroller:

- Private bucket/disk ve kısa ömürlü signed URL.
- Rastgele storage key; orijinal ad metadata'da.
- MIME, uzantı ve boyut allowlist'i.
- Filament FileUpload path-tampering koruması.
- Checksum, antivirus quarantine ve tarama sonucu.
- İndirme/export audit'i.
- Kanıt dosyasının orijinaline dokunmama; thumbnail/crop türetilmiş kopyadır.
- PDF, XLSX, DOCX, JPEG/fotoğraf, e-posta ve KML/KMZ özgün dosyasını saklama.

## 13. PDF ve dışa aktarım

Kullanıcının verdiği özel onay kapsamı, yalnız rapor/doküman çıktısına ait Blade/HTML dosyalarıdır.

```text
Immutable Report/Document Snapshot
        ↓
ReportOutputDTO
        ↓
TR veya EN Template Version
        ↓
Onaylı Blade/HTML output view
        ↓
PdfRenderer port + Queue
        ↓
Private storage + immutable output revision
        ↓
Filament notification
```

Kurallar:

- Blade yalnız DTO render eder; Eloquent sorgusu, service çağrısı veya iş mantığı içermez.
- Kullanıcı HTML'si raw basılmaz; RichEditor içeriği güvenli renderer/sanitizer üzerinden geçer.
- Dış URL veya kullanıcı kontrollü local path renderer'a verilmez.
- PDF motoru `PdfRenderer` arayüzü arkasında kalır.
- PDF paketi/motoru ayrıca onaylanmadan kurulmaz.
- Remote resource ve JavaScript çalıştırma kapalı, font/TR karakter desteği doğrulanmış olmalıdır.
- TR ve EN template sürümleri ayrıdır; zorunlu dil alanı eksikse üretim durur.
- Yeniden üretim önceki PDF'yi ezmez, yeni output revision oluşturur.
- Print CSS gerekiyorsa ayrı frontend onayı alınır.
- XLSX/CSV için Filament ExportAction, Query Service kapsamı ve formula-injection temizliği kullanılır.

## 14. Ayrı AI projesi API sınırı

### Laravel içinde bulunmayacaklar

- Model/provider SDK'sı.
- Prompt ve prompt zinciri.
- RAG, embedding, vector store ve fine-tuning.
- AI memory/clone runtime.
- Agent/tool çalışma ortamı.
- Modelin nasıl karar verdiğine dair AI mantığı.

### Laravel içinde bulunabilecekler

- Özellik bazlı yetkili Filament Action/button.
- Input DTO ve application orchestration service.
- HTTP port/adapter.
- Minimum istek/sonuç/audit metadata'sı.
- Sonucu gösteren Infolist/Notification.
- Allowlist'teki sonucu normal uygulama use-case'ine dönüştüren güvenli mapper.

Sosyal medya metni, etiket veya görsel fikri üretimi istenirse yeni bir dış capability olarak ele alınır. Laravel yalnız yetkili Action ve API istemci sınırını taşır; sonuç içerik taslağıdır ve insan inceleme/onay/yayın zincirini atlayamaz.

### Asenkron sözleşme

İstek zarfının asgari alanları:

- `schema_version`, `request_id`, `idempotency_key`, `feature`.
- `locale`, `actor_reference`, `service_scope_reference`.
- İş kaydı ve immutable revision referansı.
- İzin verilen minimum payload ve dosya referansları.
- `correlation_id` ve signed callback bilgisi.

Sonuç zarfı:

- `external_job_id`, `status`, yapılandırılmış sonuç kodu.
- Bulgular, referans/citation, uyarı ve varsa provider güven değeri.
- Önerilen/istenen uygulama aksiyonları.
- Harici run/version referansı, tamamlanma zamanı ve result hash.

Güvenlik:

- Ayrı sistem hesabı ve özellik bazlı capability grant.
- OAuth client credentials veya mTLS; callback imzası, timestamp ve replay koruması.
- Timeout, circuit breaker, sınırlı/idempotent retry ve dead-letter.
- Doğrudan DB erişimi yoktur.
- Dış servis yalnız normal application command'larını çağırabilir; Policy, transition, approval ve audit'i atlayamaz.
- AI servisi yokken rapor kaydetme, manuel kontrol ve bildirim çalışmaya devam eder.

### İlk yetki profili: rapor kontrolü

Şablon bazında açılabilen sonuç/aksiyonlar:

- `compliant`: kontrolü geçti olarak işaretle.
- `advisory_findings`: önerileri kullanıcı/yöneticiye göster.
- `revision_required`: raporu revizyona döndür ve gerekçeleri kaydet.
- `notify_employee` veya `notify_manager`: tanımlı kural üzerinden bildirim üret.
- `create_follow_up_task`: izinli görev tipinde takip işi aç.

Kullanıcının geniş AI yetkisi tercihi capability matrisiyle desteklenir; ancak her yeni aksiyon türü veri kapsamı, servis hesabı ve onay politikasıyla ayrı ayrı açılır. Final fiyat, ödeme, işe alma/çıkarma, İSG izni ve enerjilendirme gibi yüksek etkili komutlar varsayılan kapalıdır; daha sonra ayrıca açık karar gerektirir.

## 15. Outbox, queue ve tutarlılık

İş aggregate değişikliği ile Personel Hareketleri kaydı aynı DB transaction'ında yazılır. E-posta, PDF veya dış API çağrısı transaction içine alınmaz. Ayrı bir giden kutusu tablosu yoktur (D-47).

Outbox asgari alanları:

- Event ID, type/version, aggregate type/ID.
- İşlemi yapan personel veya sistem hesabı, correlation ID, occurred time.
- Immutable minimum payload ve idempotency bilgisi.


Consumer kuralları:

- Idempotent davranış ve duplicate toleransı.
- Commit sonrası çalışma.
- Retry/backoff, dead-letter ve recovery görünümü.
- Hassas içeriksiz yapılandırılmış log.

İlk job tipleri:

- Rapor occurrence üretme.
- Reminder/overdue/escalation tarama.
- Notification/e-posta teslimi.
- PDF üretimi ve dosya taraması.
- Harici rapor kontrol isteği/callback'i.
- Özel gün occurrence/hatırlatma üretimi.
- Sosyal içerik yayın isteği, webhook/reconciliation ve metrik senkronizasyonu.
- Outbox publish/recovery.

## 16. Güvenlik ve KVKK

- MFA veya kurumsal SSO hazırlığı; panel access control.
- Her Resource için Policy, her custom Action için ayrıca authorize.
- HR hassas alanlarını genel personel kartından ve teknik admin erişiminden ayırma.
- Veri minimizasyonu, kullanım amacı, retention, düzeltme/silme ve legal hold politikası.
- Private object storage, TLS, at-rest encryption ve secret vault.
- Rapor, chat, export, download, yönetici erişimi ve dış API isteği audit'i.
- Dış AI projesine gidebilecek alanların veri sınıfına göre allowlist'i.
- CV, ücret, sağlık, disiplin/performance ve kritik altyapı verileri varsayılan olarak dış AI'ya kapalıdır.
- SCADA/OT tarafına bu platformdan kontrol komutu gönderilmez.
- Sosyal hesap credential'ları secret manager dışında saklanmaz; içerikte kamuya açıklama, telif/rıza, müşteri/proje ve kritik altyapı kontrolü tamamlanmadan publish capability'si çalışmaz.
- Teknik log, business audit ve security audit ayrıdır; mesaj/doküman/prompt içeriği teknik loga yazılmaz.

## 17. Gözlemlenebilirlik

İzlenecek teknik ve operasyonel göstergeler:

- Rapor occurrence üretim hatası, due/overdue sayısı ve assignment gecikmesi.
- Notification delivery ve kritik acknowledgment süresi.
- Outbox backlog, queue latency/failure ve dead-letter.
- PDF üretim süresi/hatası; upload, antivirus ve storage büyümesi.
- Harici API latency, timeout, circuit-breaker, callback signature/replay reddi.
- Sosyal platform rate-limit/quota, auth expiry, sync lag, publish failure, reconciliation farkı ve engellenen duplicate sayısı.
- Yetkisiz erişim ve olağan dışı export/download.
- İleride stage transition retleri, handoff SLA ve entegrasyon gecikmeleri.

Filament operasyon ekranları Stats/Table/Chart Widgets ile Query Service üzerinden hazırlanır. Sentry, OpenTelemetry, Horizon veya Pulse ancak ayrı bağımlılık kararıyla değerlendirilir.

## 18. Dağıtım ve şema yönetimi

- Geliştirme, staging ve production ayrılır.
- Production'da SQLite kullanılmaz ve uygulama açılışında otomatik şema değişikliği çalışmaz.
- Web, scheduler ve queue worker süreçleri ayrıdır.
- Feature flag ile kod dağıtımı ve şema aktivasyonu ayrıştırılır.
- PDF ve dış API işleri ayrı queue/concurrency limitine sahiptir.
- Backup/restore provası ve saklama politikası kurum operasyon ekibince sahiplenilir.

Her şema değişikliği için DBA paketi:

- Amaç, etkilenen tablo/kolon/index ve veri sınıfı.
- Hacim, lock/downtime ve backward-compatibility analizi.
- Expand/contract ve uygulama sürüm sırası.
- Backup/restore ön koşulu ve roll-forward yaklaşımı.
- Feature flag ve manuel acceptance kontrol listesi.

Codex migration dosyası hazırlayabilir ancak hiçbir migration/status/rollback/reset komutu çalıştıramaz ve raw SQL ile bunu dolaşamaz. Yetkili DBA/DevOps şema sürümünü dış süreçte uygulayıp teyit etmeden özellik açılmaz.

## 19. Güvenli doğrulama ve kabul

Yalnız şu kontroller kullanılabilir:

- Değişen PHP dosyalarında `php -l`.
- `composer validate --strict` ve lock güvenlik incelemesi.
- `git diff --check`.
- Filament dosyalarında raw SQL/DB facade/explicit query zinciri taraması.
- Composer/CI içinde yasak komut taraması.
- Plugin, bağımlılık ve custom frontend değişikliği için onay kaydı kontrolü.
- Migration dosyalarında destructive işlem statik alarmı.
- Önceden hazırlanmış anonim staging ortamında manuel UAT.

Pint, Laravel/PHP test suite'i ve bütün migration/DB reset komutları doğrulama amacıyla dahi çalıştırılmaz.

İlk dalga manuel kabul örnekleri:

- Personel kartı ve giriş hesabı tek kayıttır (D-42); hiyerarşi değişiklik geçmişi korunur.
- Yönetici yalnız yetkili personel ve raporları görür.
- Aynı çalışan/şablon/dönem için ikinci rapor occurrence oluşmaz.
- Gönderilmiş rapor değişmez; revizyon yeni kayıt olur.
- Gecikmiş rapor doğru çalışan ve manager zincirine gider.
- Kritik bildirim acknowledgment ve escalation üretir.
- TR/EN PDF doğru template/revision ile oluşur ve önceki çıktı ezilmez.
- Dış AI projesi kapalıyken manuel raporlama akışı çalışır.
- Tekrarlanan callback ikinci sonuç, görev veya bildirim üretmez.
- Yetkisiz export/download/API isteği engellenir ve audit edilir.

## 20. Teknik onay kapısı

Uygulamadan önce şu girdiler onaylanır:

- Organizasyon birimleri, manager zinciri ve ilk yetki matrisi.
- İlk günlük/haftalık rapor şablonları, deadline ve escalation kuralları.
- Hassas HR alanları ve retention matrisi.
- DMS dosya türü/boyutu/saklama ve TR/EN PDF örnekleri.
- PDF motoru/Composer paketi ve gerekiyorsa print CSS izni.
- Harici AI endpoint, authentication, callback ve feature contract.
- Rapor AI sonucu/aksiyon allowlist'i ve AI'ya gönderilebilecek alanlar.
- Functional Area sorumluluk modeli ve departmanlaşma geçiş kuralı.
- Resmî sosyal hesap/platform listesi, entegrasyon capability/scope matrisi ve credential sahibi.
- Sosyal içerik RACI/onay akışı, özel gün T−4/T−3 politikası, KPI sözlüğü ve retention.
- MySQL 8.4 LTS/InnoDB, Redis ve object storage topolojisi ile DBA prosedürü.

Bu kapı ve veri tabanı tasarım kapısı kapanmadan implementation başlamaz.
