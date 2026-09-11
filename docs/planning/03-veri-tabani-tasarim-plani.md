# Konelsis Kurumsal Platform — Veri tabanı tasarım planı

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Bütün kimlikler klasik `BIGINT UNSIGNED AUTO_INCREMENT`'tir.  
**Sürüm:** 0.8 / 5 Eylül 2026

**DB-ADR-001:** Üretim veri tabanı MySQL 8.4 LTS/InnoDB olarak kilitlenmiştir; önceki PostgreSQL fiziksel varsayımları bu sürümle geçersiz kılınmıştır.

Bu planın tablo ilişkileri ve cardinality görünümü [Veri tabanı ER diyagramları](05-veri-tabani-er-diyagramlari.md) paketinde domain bazında gösterilir. ERD paketi bu belgenin yerine geçmez; iki belge DB-G8'de birlikte onaylanır ve çelişki kalırsa model dondurulmaz.

## 1. Tasarım amacı ve kesin kapı

Veri modeli şu sırayla tamamlanacaktır:

1. Personel, organizasyon, hiyerarşi, yetki ve vekâlet.
2. Şirket geneli günlük/haftalık şablon raporlama.
3. Bildirim, kritik iş, DMS, kayıtlı iletişim ve dönüşebilir Functional Area yönetişimi.
4. Projeden bağımsız Sosyal Medya hesap, içerik, özel gün, yayın ve metrik modeli.
5. İş Alım: İş Geliştirme + Teklif.
6. Tek yönlü `TKLF-n → PRJ-n` Operasyon devri.
7. Operasyon: Proje, Satın Alma, Muhasebe, Yazılım, Lojistik ve Saha workstream'leri.
8. Kalite/İSG, test/devreye alma, garanti/servis ve entegrasyonlar.

Kavramsal ERD, mantıksal tablo/kolon sözlüğü, cardinality, constraints, durum makineleri, güvenlik/retention ve read model sözleşmeleri onaylanmadan migration dosyası yazılmaz. Migration dosyaları daha sonra hazırlanabilse bile Codex hiçbir migration komutu çalıştırmaz.

## 2. MySQL 8.4 LTS modelleme standartları

- Üretim ana veritabanı MySQL 8.4 LTS, storage engine `InnoDB`'dur. Geliştirme, staging ve production aynı MySQL ana/minör sürüm ailesinde tutulur.
- Tablo ve kolon adları yalnız lowercase `snake_case` olur; işletim sistemine göre değişen tablo adı büyük/küçük harf davranışına güvenilmez.
- Karakter seti `utf8mb4` olur. Varsayılan ve Türkçe sıralama/karşılaştırma collation'ları; e-posta, personel kodu, firma adı ve TR/EN metin örnekleriyle DB-G7'de doğrulanıp dondurulur.
- Teknik primary key otomatik artan sayısal kimliktir ve MySQL'de `BIGINT UNSIGNED` tutulur. Bütün foreign key kolonları aynı fiziksel tiptedir; `BIGINT UNSIGNED` kimlik saklama standardı değildir.
- Kullanıcıya görünen `TKLF-5` ve `PRJ-5` teknik anahtar değildir.
- Tek ve hiç geri kullanılmayan global sıra, `business_number_allocations.sequence_no BIGINT UNSIGNED AUTO_INCREMENT` ile ayrılır; yıl veya legal entity ile sıfırlanmaz. Rollback veya iptal nedeniyle oluşan boşluklar kabul edilir ve numara yeniden kullanılmaz.
- Para `DECIMAL(20,4)` + ISO 4217 `currency_code`; ölçüler uygun precision/scale değerli `DECIMAL` + kontrollü birim ile tutulur. `FLOAT`/`DOUBLE` parasal veya kesin ölçüm hesabında kullanılmaz.
- İşlem zamanları UTC `DATETIME(6)` olur. Yerel iş günü gereken alanlar `DATE` + IANA timezone ile tutulur; uzun vadeli iş kayıtlarında `TIMESTAMP` tipinin 2038 aralığına güvenilmez.
- İş durumları serbest metin değildir; PHP backed enum ile eşlenen `VARCHAR` + isimlendirilmiş ve MySQL tarafından enforce edilen `CHECK` kullanılır. MySQL native `ENUM`, enum değişikliklerinde şema bağımlılığı doğurmaması için kullanılmaz.
- Çekirdek ilişkiler explicit foreign key'dir. Polymorphic/general reference yalnız DMS, chat, notification ve audit gibi yatay bağlarda kontrollü kullanılır.
- Foreign key'ler indekslenir; iş kayıtlarında silme davranışı varsayılan `RESTRICT` olur. MySQL'de deferrable constraint olmadığı için transaction içindeki yazma sırası buna göre tasarlanır.
- Submitted/approved/published/issued sürümler immutable'dır; düzeltme yeni sürüm üretir.
- Kritik işlemsel kayıtlarda hard delete yoktur; cancel/archive/supersede kullanılır.
- Taslaklarda optimistic locking için `row_version BIGINT UNSIGNED` bulunur.
- Büyük dosya DB'de tutulmaz; private object storage anahtarı ve metadata tutulur.
- `personnel_activities`, stok hareketleri ve onay kararları append-only'dir. Uygulama servisleri update/delete üretmez; physical design kapısında mümkün olan tablolar için uygulama DB rolünün UPDATE/DELETE yetkisi ayrıca daraltılır.
- Native `JSON` yalnız şablon sunum konfigürasyonu, entegrasyon zarfı, immutable snapshot ve gerçekten esnek metadata için kullanılır. Sorgulanacak çekirdek iş alanları kolon/ilişki olur; indekslenecek JSON scalar değerleri generated column'a çıkarılıp normal indekslenir.
- Çok değerli çekirdek alanlarda JSON array veya MySQL `SET` kullanılmaz; ilişki/çocuk tablo kullanılır.
- İlk metin araması seçili `CHAR`/`VARCHAR`/`TEXT` alanlarında InnoDB `FULLTEXT` ve Query Service ile değerlendirilebilir. Türkçe/İngilizce tokenizasyon ve sıralama kabul testleri yetersiz kalırsa ayrı arama servisi yeni karar kapısına alınır; bu projede vector altyapısı kurulmaz.
- KML/KMZ dosyası private object storage'da revizyonlu belge olarak saklanır. Temel konum sorgusu gerekirse açık SRID'li `POINT`/`GEOMETRY NOT NULL` ve `SPATIAL INDEX` kullanılır; ileri GIS ayrı servis/ürün kararıdır.
- MySQL'e özgü strict SQL mode, UTC connection timezone, connection charset ve deadlock retry standardı DB-G7'de DBA tarafından dondurulur.

### 2.1 PostgreSQL varsayımlarının MySQL karşılıkları

| Mantıksal ihtiyaç | MySQL 8.4 fiziksel kararı |
|---|---|
| otomatik artan sayısal kimlik | `BIGINT UNSIGNED` + tek uygulama cast/value object'i |
| Timezone-aware an | UTC `DATETIME(6)` + gerekli yerde IANA timezone kolonu |
| JSON dokümanı | Native `JSON`; sorgulanan path için generated column + index |
| Filtered/partial index | Durumla başlayan composite index veya nullable generated key + `UNIQUE` |
| Exclusion/range overlap | Owner/lock satırına `SELECT ... FOR UPDATE`, indeksli overlap sorgusu ve `CHECK(valid_until > valid_from)` |
| Array/weekday listesi | Çocuk ilişki tablosu; JSON/`SET` değil |
| Materialized view | Outbox ile beslenen projection/read-model tablosu veya zamanlanmış özet tablosu |
| Full-text | Seçili kolonlarda InnoDB `FULLTEXT`; dil kalitesi kabul kapısına bağlı |
| Mekânsal indeks | Açık SRID'li ve `NOT NULL` spatial column + `SPATIAL INDEX` |
| Tek aktif/tek seçili satır | Koşullu nullable generated column üzerinde `UNIQUE` index |

Bu tabloda belirtilen MySQL desenlerinden sapma, DB-G7 karar kaydı ve DBA onayı gerektirir.

## 3. Üst seviye ilişki haritası

```text
Organization → Legal Entity → Org Unit → Position → Personel ↔ User
                                      └→ Reporting Relationship / Delegation

Organization → Functional Area → Responsibility Assignment
                                ├→ current Team/Personel/Position owner
                                ├→ effective-dated Org Unit binding
                                └→ Social Account / Content / Special Day / Metric

Personel / Org Unit
      ↓
Report Schedule → Period → Assignment → Submission → Version → Typed Answer
                                           ├→ Review
                                           ├→ Document/Evidence
                                           └→ External Analysis Request

Domain Event → Outbox → Notification Rule → Recipient → Delivery/Ack/Escalation

Party → Business Case(sequence=5) → Opportunity/Tender → Proposal Versions
                                      code: TKLF-5
                                      ↓ accepted one-way handoff
                                    Project
                                      code: PRJ-5
                                      ├→ Primary Focus History
                                      ├→ Parallel Workstreams
                                      ├→ Gates / Handoffs / Evidence
                                      ├→ Procurement / Finance / Logistics
                                      └→ Field / Technical / Test / Service
```

## 4. Ortak çekirdek

### 4.1 Organizasyon ve referans tabloları

| Tablo | Amaç | Kritik alan/constraint |
|---|---|---|
| `organizations` | Tek Konelsis üst organizasyonu | `code UNIQUE`, default locale/timezone |
| `legal_entities` | Doğrulanmış hukuki şirketler | organization FK, country, currency, timezone; ilk sürüm ana Konelsis AŞ |
| `countries` | ISO ülke ana verisi | `code UNIQUE` |
| `currencies` | ISO para birimleri | `code UNIQUE`, decimal places |
| `units_of_measure` | Teknik/ticari birimler | dimension + code unique |
| `business_calendars` | Çalışma günü ve saatleri | legal entity/country scope |
| `calendar_days` | Tatil/özel iş günü | calendar + local date unique |
| `business_number_allocations` | TKLF/PRJ için tek global ve geri kullanılmayan sıra | `sequence_no BIGINT UNSIGNED AUTO_INCREMENT`; correlation/purpose/status; boşluk kabul edilir |
| `security_classifications` | Public/Internal/Confidential/Restricted | rank/order unique |
| `retention_policies` | Saklama ve legal hold varsayımları | class, duration, disposition |

Şirket bilgi tabanındaki grup şirketi veya ülke listeleri otomatik `legal_entities` kaydı olmaz; hukuk/finans tarafından doğrulanan varlıklar eklenir. Ofis, temsilcilik, proje varlığı, SPV/JV ve geçmiş referans ayrı kavramlardır.

### 4.2 İşlemci kimliği

| Tablo | Amaç |
|---|---|
| `personnel` | Giriş hesabı, locale/timezone ve kimlik durumu |
| `personnel` | Audit ve komutlarda tek actor FK; tür `user/service` ve yalnız biri dolu |

Personel kaydı hem çalışan kartı hem giriş hesabıdır (D-42); ayrı kullanıcı tablosu yoktur. Preboarding veya ayrılmış personel kaydı giriş bilgisi olmadan da durabilir. Dış AI projesi personel gibi görünmez; ayrı bir sistem hesabıdır (D-43).

### 4.3 Dönüşebilir Functional Area yönetişimi

`FunctionalArea`, resmî departman (`OrgUnit`) veya proje değildir. Bugün ekip/personel tarafından yürütülen bir alan, ileride efektif tarihli olarak bir departmana bağlanabilir; domain kayıtlarının kimliği ve geçmiş sahiplik snapshot'ları değişmez.

| Tablo | Ana alanlar | Kritik kurallar |
|---|---|---|
| `functional_areas` | organization, stable code, default locale/timezone, classification, status, active dates | code organization içinde unique; `social_media` ilk kayıttır |
| `functional_area_translations` | area, locale, name, description | area+locale unique; ilk diller TR/EN |
| `functional_area_role_definitions` | area, role code, TR/EN ad, capability set, status | area+role code unique; alana özgü rol kataloğu |
| `functional_area_responsibilities` | area, role code, target personnel/team/position, valid_from/until, is_primary | hedeflerden tam biri dolu; tarihçeli; tek aktif function owner |
| `functional_area_org_unit_bindings` | area, org_unit, binding_type owner/contributor/governance, valid dates | departmanlaşma ve çok birimli katılım tarihçeli |
| `functional_area_transition_events` | old/new governance mode, effective_at, approved_by, reason | append-only; açık iş transfer sonucu bağlıdır |
| `functional_area_transition_items` | transition event, object type/id, previous/new owner snapshot, transfer action, outcome | departmanlaşmada etkilenen açık görev/onay/bildirim/sorumlulukların satır bazlı ve tekrar çalıştırılabilir transfer defteri |

Sosyal Medya sorumluluk rolleri ilk sürümde `function_owner`, `content_creator`, `reviewer`, `approver`, `publisher` ve `analyst` olur. Aktif tek function owner, koşullu nullable generated guard + unique index ile korunur. Tarih aralığı çakışmaları Functional Area kök satırını kilitleyen 5.2 servis deseniyle engellenir.

Departmanlaşmada eski team/personel sorumluluğu efektif tarihle kapanır, yeni OrgUnit bağı ve gerekli roller açılır. Açık task/approval/notification sorumlulukları transfer kaydıyla yeniden çözülür; geçmiş alıcı/owner snapshot'ı güncellenmez. Bu ortak model EAV veya dinamik modül üreticisi değildir; her alan kendi domain tablolarını korur.

## 5. Personel ve organizasyon hiyerarşisi

### 5.1 Ana tablolar

| Tablo | Ana alanlar | Kurallar |
|---|---|---|
| `org_units` | legal_entity, code, TR/EN name, unit_type, active dates | code legal entity içinde unique |
| `org_unit_relations` | child, parent, relation_type, valid_from/to | child != parent; geçerlilik çakışması engellenir; döngü servis kontrolü |
| `positions` | org_unit, code, TR/EN title, grade, managerial_level, headcount | aktif kod unit içinde unique |
| `personnel` | personnel_no, user nullable, ad/soyad, work contact, locale, timezone, status, join/leave | personnel_no unique; aktif personelde user zorunluluğu uygulama invariant'ı |
| `personnel_private_profiles` | personnel, encrypted identity/bank/health fields | genel profil/read model dışında; dar HR Policy |
| `employments` | personnel, legal_entity, type, valid_from/to, status | tarih aralığı çakışma kuralları |
| `position_assignments` | personnel, position, valid_from/to, is_primary, allocation_pct | aynı anda tek primary pozisyon; allocation toplam kontrolü |
| `reporting_relationships` | personnel, manager, relation_type, scope, valid_from/to | self-link yok; line/functional/project ayrımı |
| `teams` | code, name, owner unit, status | proje dışı ve proje ekipleri ayrıştırılır |
| `team_memberships` | team, personnel, role, valid_from/to | tarihçeli üyelik |
| `delegations` | grantor, delegate, capability/scope, valid dates, approved_by | süresi/kapsamı dışında geçersiz; geri alma geçmişi |
| `competencies` / `personnel_competencies` | yetkinlik, seviye, doğrulayan, tarih | self-declaration ve verified ayrılır |
| `certifications` / `personnel_certifications` | belge no, geçerlilik, dosya revizyonu | expiry trigger üretir |
| `trainings` / `training_attendances` | eğitim ve katılım/sonuç | personel gelişim geçmişi |
| `personnel_status_histories` | önceki/yeni durum, neden, zaman | append-only |

### 5.2 Tarih aralığı ve eşzamanlılık koruması

MySQL'de range/exclusion constraint bulunmadığı için `org_unit_relations`, `employments`, `position_assignments`, `reporting_relationships`, `team_memberships` ve `delegations` aynı ortak application service deseniyle yazılır:

1. Değişmezliği sahiplenen personnel/position/org-unit gibi kök satır `SELECT ... FOR UPDATE` ile kilitlenir.
2. Aynı kapsamın `[valid_from, valid_until)` aralığıyla kesişen aktif kayıtları uygun composite index üzerinden aranır.
3. İzin verilmeyen overlap varsa transaction reddedilir; uygunsa yeni tarihçeli satır eklenir.
4. Satır içi tarih sırası `CHECK(valid_until IS NULL OR valid_until > valid_from)` ile ayrıca korunur.

CSV/import, entegrasyon ve yönetim Action'ları bu servisi atlayamaz. DBA tarafından yapılan toplu aktarım öncesi ve sonrası overlap doğrulama raporu zorunludur. Bu desenin concurrency kabul senaryosu iki eşzamanlı atamanın yalnız birinin başarılı olmasıdır.

### 5.3 Personel durum makinesi

```text
preboarding → active ↔ on_leave
active/on_leave → suspended
active/on_leave/suspended → separated → archived
```

Ayrılma işlemi aktif pozisyon, team, delegation, approval assignment ve proje rolü için kontrollü kapanış görevleri üretir; geçmiş kayıtları silmez.

### 5.4 İleri İK tabloları

- `leave_types`, `leave_requests`, `leave_approvals`.
- `attendance_entries`, `timesheets`, `timesheet_lines`.
- `expense_claims`, `expense_lines`, `expense_approvals`.
- `workforce_requests`, `staffing_options`, `resource_assignments`.
- `job_requisitions`, `job_post_versions`, `candidates`, `applications`, `interviews`.
- `performance_cycles`, `performance_records`, `development_actions`.

Aday/CV, ücret, sağlık, disiplin ve performans alanları restricted sınıf, şifreleme ve ayrı retention politikasına sahiptir. Muhasebe işe alma kararı vermez; onaylı işe girişten sonra finansal/bordro handoff'u alır.

## 6. Şirket geneli şablonlu raporlama

### 6.1 Şablon tabloları

| Tablo | Ana alanlar | Kurallar |
|---|---|---|
| `report_templates` | code, kind, owner_unit, allowed_scope, status | code unique; günlük/haftalık/aylık/ad-hoc/olay |
| `report_template_versions` | template, version_no, effective dates, status, classification, published_by/at | template+version unique; published immutable |
| `report_sections` | template_version, section_key, TR/EN title/help, sort | key sürüm içinde unique |
| `report_questions` | section, field_key, type, required, unit, validation_config, sort, KPI flag | field_key template version içinde unique |
| `report_question_options` | question, option_key, TR/EN label, sort | option key question içinde unique |
| `report_workflow_bindings` | template_version, reviewer/escalation policy version | başladığı policy sürümüne sabit |

İlk cevap tipleri:

`short_text, long_text, rich_text, integer, decimal, money, percentage, boolean, date, datetime, single_choice, multi_choice, personnel, org_unit, project, document, repeated_group`

Filament bileşen/yerleşim tercihleri yalnız native `JSON` tipindeki `validation_config` ve `presentation_config` alanlarında bulunabilir; soru kimliği, tipi, zorunluluğu, birimi ve KPI eşlemesi normal kolondur. Filtrelenecek veya sıralanacak JSON scalar değerleri generated column'a çıkarılır.

### 6.2 Takvim ve beklenen rapor

| Tablo | Amaç ve ana kurallar |
|---|---|
| `report_schedules` | template, frequency, timezone, open/due/grace, active dates, resolver config |
| `report_schedule_weekdays` | schedule + ISO weekday unique; haftalık tekrar günlerinin normalize listesi |
| `report_schedule_targets` | organization, unit, position, team veya personnel hedefi ve hariç tutma |
| `report_periods` | schedule + start/end unique; o tarihteki published template version snapshot |
| `report_assignments` | period + personnel unique; unit/position/reviewer/due_at snapshot; waiver fields |

`report_assignments` gönderenin rapor yükümlülüğüdür. Rapor hiç gönderilmezse de satır var olduğu için eksik/gecikmiş durum ölçülebilir. Sonradan manager/departman değişikliği geçmiş dönemin snapshot'ını değiştirmez.

### 6.3 Submission ve tipli cevaplar

| Tablo | Amaç ve ana kurallar |
|---|---|
| `report_submissions` | assignment ile 1:1 kök; current version, status, first_submitted, approved, locked |
| `report_submission_versions` | submission + version_no unique; immutable cevap seti, change summary, submitted actor/time |
| `report_answers` | version + question + row_key unique; type snapshot ve tipli nullable değer kolonları |
| `report_answer_options` | multi-choice seçimleri |
| `report_answer_documents` | cevap/kanıt ile document revision bağı |
| `report_reviews` | version, reviewer, decision, comment, decided_at |
| `report_status_transitions` | from/to, actor, reason ve zaman; append-only |
| `report_metric_facts` | KPI işaretli cevapların dönemsel normalize projection'ı |

`report_answers` için cevap tipine uygun yalnız bir değer kolonunun dolmasını sağlayan CHECK bulunur. Tekrarlı gruplar `row_key` ile gruplanır; raporlanacak kritik satırlar sonradan ayrı domain aggregate'e dönüştürülür.

### 6.4 Rapor durumları

```text
assignment: planned → open → waived | closed

submission: not_started → draft → ready_for_check
            → external_check_pending (gerekliyse)
            → submitted → under_review
            → approved → locked
            → revision_required → draft(new version)
            → rejected
```

`due_soon`, `overdue` ve `escalated` submission status değildir. `due_at` geçmiş, waiver yok ve gönderilmiş sürüm yoksa `ReportBecameOverdue` olayı idempotent olarak bir kez üretilir.

## 7. Bildirim, kritik iş ve görevler

### 7.1 Notification modeli

| Tablo | Amaç |
|---|---|
| `notification_rules` / `notification_rule_versions` | olay, severity, resolver, kanal, reminder, escalation ve dedupe politikası |
| `notification_rule_recipients` | kişi/pozisyon/manager/birim/proje rolü/functional-area rolü resolver tanımı |
| `notifications` | olaydan üretilen bildirim kökü ve güvenli deep link |
| `notification_recipients` | olay anında çözülen kullanıcı/personel snapshot'ı |
| `notification_delivery_attempts` | kanal, attempt, queued/delivered/failed/bounced |
| `notification_receipts` | unread/read zamanları |
| `notification_acknowledgements` | kritik sorumluluk kabulü, yorum ve zaman |
| `notification_escalations` | seviye, önceki/sonraki alıcı ve neden |
| `notification_preferences` | kapatılabilir kanallar; kritik zorunlu kuralı geçersiz kılamaz |

Teslimat, okundu ve acknowledgment tek status alanında birleştirilmez.

### 7.2 Kritik iş ve genel görev

| Tablo | Amaç |
|---|---|
| `tasks` | bağımsız/functional-area/proje/rapor bağlamlı iş, assignee, owner, due, priority, status |
| `task_assignments` | birden fazla kişi/rol ve sorumluluk tipi |
| `task_dependencies` | işi açan predecessor ve hard/soft bağımlılık |
| `business_alerts` | severity, impact, source event, owner, due, state |
| `business_alert_acknowledgements` | kim, ne zaman sorumluluğu aldı |
| `business_alert_resolutions` | çözüm, kanıt, onaylayan ve zaman |

İlk trigger'lar: rapor deadline/review SLA, teklif son tarihi, onay gecikmesi, proje dependency, stok eşiği, teslimat, test/NCR, İSG, vergi/ödeme, özel gün T−4/T−3, sosyal içerik onay/yayın, credential süresi, metrik senkronizasyon gecikmesi ve entegrasyon hatası.

## 8. DMS, PDF ve kayıtlı iletişim

### 8.1 DMS

| Tablo | Ana sorumluluk |
|---|---|
| `file_objects` | disk/key, sha256, bytes, MIME, tarama/karantina, uploader |
| `documents` | document_no, type, owner, classification, retention, current revision |
| `document_revisions` | immutable revision_no/code, language, status, purpose, prepared/approved/issued |
| `document_revision_files` | revizyon ile orijinal/türetilmiş dosya bağı |
| `document_links` | rapor, görev, functional area, sosyal içerik sürümü, teklif, proje, mesaj gibi business object bağı |
| `document_reviews` | revizyon bazlı inceleme/onay |
| `document_distributions` | kime, hangi revizyon, ne zaman dağıtıldı |
| `document_acknowledgements` | iş planı/prosedür gibi belgenin kişi bazlı okunma/kabulü |
| `transmittals` / `transmittal_items` | dış gönderim paketi ve gönderilen exact revision snapshot |
| `document_templates` / `document_template_versions` | TR/EN rapor/kurumsal çıktı şablonları |
| `generated_outputs` | source revision, template version, PDF/XLSX/DOCX dosyası, status/hash |
| `legal_holds` | normal retention disposal'ını durduran hukuki kayıt |
| `legal_hold_documents` | legal hold ile korunan document/revision kapsamı, ekleyen actor ve zaman | hold+document/revision tekrarı engellenir; hold kapanmadan disposal yapılamaz |

Doküman onayı, dağıtımı, personelin kabulü ve chat okundu bilgisi farklı kayıtlardır.

### 8.2 Konuşma dizileri

| Tablo | Ana alan/constraint |
|---|---|
| `conversations` | company/functional-area/department/group/direct/project türü, scope, classification, history policy |
| `conversation_memberships` | user, role, joined/left, history_visible_from |
| `messages` | conversation_sequence unique, yazan personel, reply_to, sent/redacted |
| `message_versions` | düzenleme geçmişi ve immutable content snapshot |
| `message_mentions` | mention edilen kullanıcı/personel |
| `message_attachments` | file/document revision bağı |
| `conversation_read_cursors` | kullanıcı bazlı son okunan sequence |
| `message_business_links` | mesajdan üretilen görev/karar/kritik iş bağlantısı |

Silme yerine erişim politikası ve gerekiyorsa redaction uygulanır. Yönetici zinciri özel konuşma erişimi üretmez.

### 8.3 E-posta alımı

- `mailboxes`.
- `inbound_email_messages`, `inbound_email_recipients`, `inbound_email_attachments`.
- `inbound_email_business_links`, `email_processing_attempts`.

Internet Message-ID + içerik hash ile duplicate engellenir. Durum:

```text
received → validated → processed
validated → needs_review
received/validated → failed → retrying → processed | permanently_failed
```

## 9. Projeden bağımsız Sosyal Medya alanı

Sosyal medya tablolarının tamamı `functional_area_id` ile `social_media` alanına bağlanır. Hiçbir kök sosyal hesap, içerik, yayın, özel gün veya metrik kaydında zorunlu `project_id` bulunmaz. Gelecekte kamuya açıklanması onaylanmış bir proje/party/business case bağlantısı gerekiyorsa ayrı, opsiyonel ve Policy kontrollü link tablosu eklenir; kayıt sahipliği projeye geçmez.

### 9.1 Sosyal hesap ve bağlantı tabloları

| Tablo | Ana alanlar | Kritik kurallar |
|---|---|---|
| `social_platforms` | code, TR/EN name, status | doğrulanmış platform kataloğu; capability varsayımı taşımaz |
| `social_accounts` | functional_area, platform, display_name, normalized_handle, public_url, external_account_id nullable, locale, timezone, mode, status | platform+external ID ve platform+normalized handle unique; proje FK'sı yok |
| `social_account_identifier_histories` | account, previous/new handle veya external ID, valid dates, verified_by | hesap adı değişse geçmiş bağlantılar korunur |
| `integration_connection_capabilities` | connection, capability, scope snapshot, granted/verified/expired times | profile/metrics/publish/schedule/webhook ayrı capability |
| `social_account_connections` | account, connection, status, active dates | hesap/provider başına tek aktif bağlantı generated guard + unique |
| `social_connection_health_checks` | connection, checked_at, outcome, safe error code | credential veya payload içeriği yok |
| `social_webhook_receipts` | platform, provider event ID, payload hash/reference, received/processed time, status | platform+provider event ID unique; replay ikinci işlem üretmez |
| `social_sync_runs` | account, sync kind, window, cursor, started/completed, outcome | read-only profil/metrik reconciliation geçmişi |

`mode` ilk sürümde `manual` veya `connected` olur. Connection olmaması hesabı kullanım dışı bırakmaz; manuel yayın URL/kanıtı ve manuel metrik girişi çalışır. Sosyal platform token ve secret değerleri Personel Hareketleri kaydında ve logda maskelenir.

### 9.2 İçerik, onay ve yayın tabloları

| Tablo | Ana alanlar | Kritik kurallar |
|---|---|---|
| `social_campaigns` | area, code, TR/EN name, purpose, owner, active dates, status | kampanya opsiyoneldir; code area içinde unique |
| `social_content_items` | area, campaign/special occurrence nullable, kind, owner, priority, due_at, lifecycle, current_version | proje olmadan açılır; hard delete yok |
| `social_content_versions` | item, version_no, brief/objective/brand notes, status, content_hash, created/submitted/approved actors/times | item+version unique; approved immutable |
| `social_content_version_translations` | version, locale, title, caption/body, CTA, alt_text | version+locale unique; ilk diller TR/EN |
| `social_content_assets` | version, exact document_revision, usage_role, sort, rights/consent reference | mutable dosya yoluna değil DMS revizyonuna bağlanır |
| `social_content_reviews` | version, reviewer, review type, decision, comment, decided_at | exact version/hash incelemesi |
| `social_content_targets` | content version, account, locale, planned/scheduled UTC, local timezone snapshot, status | target exact approved version+hesaptır; duplicate target engellenir |
| `social_publications` | target, idempotency_key, source manual/API, external post ID/URL, published_at, payload hash, state | idempotency ve provider external post unique; kanıtsız published yok |
| `social_publication_attempts` | publication request, attempt no, provider request ID, duration, outcome, safe error | append-only; retry geçmişi |
| `social_publication_corrections` | publication, action removed/corrected, reason, evidence, actor/time | yayımlanmış kaydı silmeden dış platform düzeltmesi |

Generic approval kaydı exact `social_content_version_id`, version/hash ve policy version taşır. Yalnız `approved` sürüm hedeflenebilir/yayımlanabilir; bu satırlar arası invariant, content/version/target köklerini sabit sırada kilitleyen application service transaction'ıyla korunur.

Durumlar birbirine karıştırılmaz:

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

Onay sonrası metin, varlık, hesap, dil veya planlanan zaman değişirse yeni version/target oluşur ve etki matrisine göre yeniden onay istenir.

### 9.3 Özel gün ve T−4/T−3 modeli

Özel gün kataloğu yalnız sosyal medyaya gömülmez; ileride başka Functional Area'lar da aynı doğrulanmış occurrence'ı kullanabilir.

| Tablo | Ana alanlar | Kritik kurallar |
|---|---|---|
| `special_day_calendars` | organization/country scope, timezone, owner, status | kaynak ve yıllık doğrulama sahibi |
| `special_day_definitions` | calendar, stable code, category, recurrence kind, priority, active dates | kavram kimliği; kesin tarih değildir |
| `special_day_translations` | definition, locale, name, description | definition+locale unique |
| `special_day_occurrences` | definition, year, local date/time, timezone, source/reference, verification state | definition+year+local date unique; hareketli gün doğrulanmadan hazır sayılmaz |
| `social_special_day_rules` | area, occurrence scope, relevance, account/language optional, policy version | hangi özel günün sosyal plan gerektirdiği |
| `social_reminder_policy_versions` | area, version, day basis, timezone/send time, active dates | yayımlanan sürüm immutable |
| `social_reminder_policy_steps` | policy version, step code, offset days, severity, readiness predicate, ack/escalation | varsayılan T−4 reminder ve T−3 escalation ayrı adım |
| `social_reminder_step_recipients` | policy step, recipient resolver kind, Functional Area role/direct target, escalation order | bir policy adımının birden fazla alıcı kuralını normalize eder; step+resolver+target tekrarı engellenir |
| `social_special_day_plans` | occurrence+area, owner, linked content, readiness status, acknowledged_at | occurrence+area unique |
| `social_special_day_reminder_instances` | plan, policy step, trigger_at UTC, status, dedupe key | plan+step unique; dedupe key unique |
| `social_reminder_instance_recipients` | reminder instance, belirlenen personel, role/source snapshot, notification, delivery/ack state | tetik anındaki gerçek alıcıyı değişmez kaydeder; instance+personel unique |

Readiness değerleri `unplanned → owner_assigned → drafting → review_pending → approved → scheduled → published` olarak ilerler; yetkili `not_applicable`, `missed` ve `cancelled` yan sonuçları ayrıca bulunur.

T−4 adımı Functional Area `content_creator` ve `function_owner` rollerine ilk bildirim/görevi üretir. T−3 adımı, sorumlu atanmış ve içerik readiness'i en az `drafting` değilse severity yükseltip `function_owner` ve tanımlı eskalasyon alıcısına gider; yetkili `not_applicable` kararı istisnadır. Bildirimi okumak readiness değildir; acknowledgment, içerik hazırlığı ve onay ayrı doğrulanır.

Occurrence tarihi değişirse daha önce çalışmış reminder güncellenmez; eski instance `superseded/cancelled` olur, yeni occurrence/policy snapshot'ından yeni instance üretilir. T−4'ten geç girilen occurrence ilk taramada tek gecikmiş kritik olay üretir. Gün hesabı policy sürümünde `calendar_day` veya `business_day` olarak açıkça seçilir.

### 9.4 Sosyal metrik ve KPI tabloları

| Tablo | Ana alanlar | Kritik kurallar |
|---|---|---|
| `social_metric_definitions` | canonical code, TR/EN name, subject account/content, unit, value type, aggregation semantics, version | aynı isimli provider metriklerini otomatik eşitlemez |
| `social_platform_metric_mappings` | platform, API version, external metric key, definition/version, valid dates | mapping sürüm/tarihçeli |
| `social_metric_ingestion_batches` | account, source API/manual, window, provider request ID, payload hash/evidence, outcome | provider tekrarında source dedupe unique |
| `social_account_metric_observations` | batch, account, definition, period start/end, observed_at, decimal value | account+definition+period+source revision unique |
| `social_publication_metric_observations` | batch, publication, definition, period start/end, observed_at, decimal value | publication+definition+period+source revision unique |
| `social_metric_sync_cursors` | account, provider/capability, cursor, last_success_at | secret/payload içermez |
| `social_kpis` | functional area, stable code, TR/EN name, subject kind, unit, status | KPI kimliği area+code içinde unique; formülden ve dönemsel sonuçtan bağımsız kök |
| `social_kpi_formula_versions` | KPI, version no, formula definition, effective dates, status | yayımlanmış formül immutable; dashboard geçmişini sessizce değiştirmez |
| `social_kpi_formula_inputs` | formula version, canonical metric definition, input alias, aggregation/window | formül girdilerini normalize eder; version+alias unique |

Oranlar ham provider alanı olarak ortaklaştırılmaz; versioned KPI formülü ve Query Service ile türetilir. Raw provider payload gerekiyorsa token/PII temizlendikten sonra private object storage'da kısa retention ile saklanır; DB yalnız hash, storage reference ve provenance metadata'sı taşır.

### 9.5 Sosyal medya kritik indeksleri ve invariants

- `(functional_area_id, lifecycle/status, due_at/planned_at)` içerik ve hazırlık kutuları için composite index.
- `(platform_id, external_account_id)` ve `(platform_id, normalized_handle)` unique; nullable external ID için generated guard deseni.
- Bir hesap/provider için tek aktif connection ve bir Functional Area için tek aktif function owner: nullable generated guard + unique index.
- `(content_item_id, version_no)`, target exact version+account+planned time ve yayın `idempotency_key` unique.
- `(platform_id, external_post_id)` provider kimliği varsa unique.
- `(special_day_plan_id, policy_step_id)` ve reminder `dedupe_key` unique.
- Metric observation için subject+definition version+period+source revision unique.
- Tüm anlık zamanlar UTC `DATETIME(6)`; planlama ve occurrence kayıtları yerel timezone snapshot'ı taşır.
- Owner, reviewer, approver, publisher ve notification recipient işlem anında snapshot edilir.
- Approved/published version, publication attempt, metric observation ve çalışmış reminder yerinde değiştirilmez.

## 10. Party, İş Alım ve kod soy zinciri

### 10.1 Party ana verisi

- `parties`, `party_roles`, `organization_profiles`, `person_profiles`.
- `contact_relationships`, `addresses`, `communication_points`.
- `party_licenses`, `party_certificates`, `party_annual_reviews`.

Customer, supplier, subcontractor, partner, employer ve investor ayrı kopyalar değil, tek Party'nin tarihçeli rolleridir.

### 10.2 Business case ve kodlar

`business_cases` tek ticari iş köküdür:

- otomatik artan sayısal kimlik `id BIGINT UNSIGNED`.
- `business_number_allocations` üzerinden ayrılmış global `sequence_no BIGINT UNSIGNED UNIQUE`.
- legal entity, primary party, lifecycle segment, outcome, row_version.

`business_codes`:

- business_case FK, code_kind `offer/project`, aynı sequence_no.
- `formatted_code`: `code_kind` ve `sequence_no` üzerinden üretilen STORED generated column; `TKLF-{sequence_no}` veya `PRJ-{sequence_no}`.
- issued_at/by, predecessor code.
- business_case+kind ve formatted_code unique.

`business_cases` üzerinde `(id, sequence_no)` unique key ve `business_codes` üzerinde `(business_case_id, sequence_no)` composite foreign key bulunur; böylece kod ile iş kökünün sıra numarası DB seviyesinde eşleşir. `sequence_no`, generated code'un bulunduğu `business_codes` tablosunda `AUTO_INCREMENT` değildir.

`TKLF-5` kayıp/iptal edilirse `5` yeniden kullanılmaz. Operasyon devrinde yalnız aynı business case için `PRJ-5` üretilebilir; iki kodla arama aynı soy zincirini bulur.

### 10.3 İş Alım aggregate'leri

- `opportunities`, `opportunity_stage_histories`, `business_development_activities`.
- `tender_sources`, `tender_notices`, `tender_notice_versions`, `tender_requirements`, `tender_deadlines`.
- `proposals`, `proposal_versions`, `proposal_documents`.
- `estimate_versions`, `estimate_lines`, `pricing_scenarios`, `boq_items`.
- `compliance_items`, `deviations`, `brand_items`, `responsibility_matrix_items`.
- `contracts`, `contract_versions`, `contract_parties`, `contract_documents`, `contract_obligations`, `contract_milestones`.
- İş Alım aggregate'leri ayrı onay tabloları üretmez; 14. bölümdeki ortak `approval_policies`, `approval_policy_versions`, `approval_steps`, `approval_requests`, `approval_request_steps` ve `approval_decisions` exact teklif/sözleşme/devir sürümünü subject olarak kullanır.
- `operation_handoffs`, `operation_handoff_versions`, `handoff_items`, `handoff_reviews`.

`contracts` kabul edilmiş ticari ilişkinin stable köküdür. Değişen hükümler `contract_versions` üzerinde immutable sürümlenir; taraf rolleri, belge revizyonları, yükümlülükler ve kilometre taşları exact sözleşme sürümüne bağlanır. Operasyon devri yalnız onaylı/kabul edilmiş exact sözleşme veya açıkça onaylanmış LOI/NTP snapshot'ını referanslayabilir.

İş Alım üst akışı:

```text
business_development → offer_preparation → offer_review
→ submitted → negotiation → won
→ handover_preparing → handover_review → handover_accepted
→ OPERATION
```

Kayıp ve iptal acquisition terminal durumlarıdır. `operation → acquisition` geçişi yoktur.

Handoff kabulü tek transaction davranışında:

1. Handoff sürümünü ve final teklif/sözleşme snapshot'ını kilitler.
2. Alıcı Proje Grubu kabulünü kaydeder.
3. Aynı sıra numarasıyla project code üretir.
4. `projects` ve başlangıç workstream/gate instance'larını oluşturur.
5. Business case segmentini operation yapar.
6. Outbox event yazar.

Hata halinde kısmi `PRJ-n`, project veya handoff kabulü kalmaz.

## 11. Operasyon ve proje orkestrasyonu

### 11.1 Proje kökü

`projects`:

- business_case ve project business code 1:1 unique.
- name, customer, legal entity, project manager.
- country/timezone, start/finish, overall_status, current macro gate.
- primary_focus_workstream FK, criticality profile ve cover document/image.

Genel durum yalnız `opening, active, acceptance, warranty, closed, suspended, cancelled` değerlerini taşır. `procurement`, `field` veya `software` genel status değildir.

`project_components` aynı projede GES, HES, RES, BESS, EMS, ENH, trafo/şalt, SCADA, inşaat/mekanik ve diğer teknik kapsamların birlikte bulunmasını sağlar.

### 11.2 Workstream ve focus

Başlangıç `operation_group_definitions`:

1. PROJECT
2. PROCUREMENT
3. ACCOUNTING
4. SOFTWARE
5. LOGISTICS
6. FIELD

| Tablo | Amaç |
|---|---|
| `project_workstreams` | proje+grup unique; owner, dates, status, progress, block |
| `workstream_dependencies` | predecessor/successor, FS/SS/FF/SF, lag ve hard flag |
| `project_focus_histories` | tek açık primary focus, changed_by, reason ve zaman |
| `work_packages` | workstream içindeki yürütülebilir kapsam |
| `work_package_dependencies` | ayrıntılı bağımlılık grafiği |

Workstream durumu:

```text
not_ready → ready → active → review → completed
active/review → blocked → active
not_ready/ready/active → waived | cancelled
```

Hard dependency tamamlanmadan bağlı iş `ready/active` olamaz; koşullu geçiş yalnız yetkili waiver ile mümkündür. Dependency graph döngüsü application service tarafından reddedilir.

Primary focus yönetimin o anda izlediği ana gruptur; diğer workstream'leri durdurmaz ve proje lifecycle statusu değildir.

### 11.3 Gate, handoff ve proje kontrolü

- `stage_templates`, `stage_template_versions`, `stage_nodes`, `stage_dependencies`, `stage_requirement_definitions`.
- `project_stage_instances`, `project_stage_requirements`, `stage_evidence`, `stage_reviews`, `stage_waivers`.
- `department_handoffs`, `department_handoff_versions`, `department_handoff_items`, `department_handoff_reviews`.
- `wbs_nodes`, `cbs_nodes`, `wbs_cbs_mappings`.
- `schedule_baselines`, `budget_baselines`, `milestones`, `progress_snapshots`.
- `project_tasks`, `project_issues`, `project_risks`, `delay_events`, `recovery_actions`.
- `project_changes`, `commercial_clarifications`, `commercial_exposures`, `project_decisions`.

Risk, issue, delay, change ve ticari kayıp aynı tabloda birleştirilmez.

Gate durumu:

```text
not_started → preparing → ready_for_review → approval_pending
approval_pending → passed | conditionally_passed | rejected
rejected → preparing
passed/conditionally_passed → reopened
```

Gate status formdan doğrudan düzenlenemez; hard dependency, evidence ve approval policy servis tarafından doğrulanır.

`stage_requirement_definitions` şablon sürümündeki tekrar kullanılabilir requirement tanımıdır. `project_stage_requirements` ise proje stage instance'ı açılırken exact tanım sürümünden üretilen; applicability, zorunluluk, owner, teslim ve sonuç snapshot'ını taşıyan yürütme kaydıdır. Definition sonradan değişse bile başlamış stage geçmişi değişmez.

## 12. Operasyon alt alanlarının tablo kataloğu

### Satın Alma

- `catalog_categories`, `catalog_items`, `brands`, `approved_equivalents`.
- `purchase_requisitions`, `purchase_requisition_lines`.
- `supplier_rfqs`, `supplier_rfq_lines`, `supplier_rfq_invitees`.
- `supplier_quotes`, `supplier_quote_versions`, `supplier_quote_lines`.
- `bid_comparisons`, `bid_comparison_lines`, `award_recommendations`.
- `purchase_orders`, `purchase_order_versions`, `purchase_order_lines`, `delivery_schedules`.

Her talep/sipariş satırı project, WBS, cost code, item ve required-by tarihiyle ilişkilidir.

### Lojistik ve stok

- `shipments`, `shipment_items`, `carriers`, `customs_records`.
- `goods_receipts`, `goods_receipt_lines`, `receipt_inspections`.
- `warehouses`, `warehouse_locations`, `bins`.
- `stock_lots`, `serialized_items`, `stock_reservations`.
- `inventory_transactions`, `stock_counts`, `stock_count_lines`, `stock_transfers`, `stock_transfer_lines`.

Stok bakiyesi türetilmiş projection; immutable inventory transaction doğruluk kaynağıdır.

### Muhasebe ve proje finans kontrolü

- `project_budgets`, `budget_versions`, `budget_lines`, `commitments`, `actual_cost_references`.
- `supplier_invoices`, `invoice_lines`, `invoice_matches`.
- `tax_obligations`, `payment_requests`, `payment_request_invoices`, `payments`, `cash_flow_forecasts`.
- `progress_claims`, `progress_claim_versions`, `retentions`.

Resmî muhasebe/yevmiye Zirve'de kalır; CRM external ID, project/cost code ve operasyonel snapshot tutar.

### Teknik, saha, kalite ve devreye alma

- `engineering_deliverables`, `engineering_revisions`, `technical_requirements`.
- `work_plans`, `procedures`, `method_statements` ve DMS acknowledgment bağları.
- `field_daily_records` yerine mümkün olduğunda genel report submission + typed project metric bağları.
- `crews`, `crew_memberships`, `equipment_usages`, `installed_quantities`, `site_photos`.
- `inspections`, `ncrs`, `corrective_actions`, `incidents`, `permits`, `punch_items`, `quality_evidence`.
- `test_plan_templates`, `test_plan_versions`, `test_plan_step_definitions`, `test_executions`, `test_execution_steps`, `test_evidence`.
- `test_equipment`, `calibration_certificates`, `test_execution_equipment`, `commissioning_packages`, `commissioning_package_tests`, `commissioning_package_assets`, `acceptance_certificates`.
- `installed_assets`, `warranties`, `service_requests`, `work_orders`, `maintenance_plans`.

`test_plan_step_definitions` yayımlanmış test planı sürümündeki adım, sıralama, acceptance criterion ve witness/hold point tanımıdır. `test_execution_steps` belirli bir execution için exact definition'a bağlı gerçek sonuç, karar, actor, zaman ve kanıt durumunu taşır; tanım ile icra aynı tabloda birleştirilmez. Test aşamalarının kurumsal adları, witness/hold point'leri ve kabul sahipleri DB-G5'ten önce onaylanacaktır.

## 13. Ayrı AI projesi için genel API metadata modeli

Bu tablolar AI uygulaması değildir; harici servise yapılan kontrollü entegrasyonun teknik/audit kayıtlarıdır.

| Tablo | Ana alan/kurallar |
|---|---|
| `external_capabilities` | `report.standard_control` gibi onaylı capability kodu; model/prompt/provider içermez |
| `service_capability_grants` | sistem hesabı, capability, scope, valid dates, action allowlist |
| `external_analysis_requests` | capability, business ref, requester, immutable source revision, idempotency key, status, hash, timeout |
| `external_analysis_artifacts` | izinli document/report revision referansı ve rolü |
| `external_analysis_attempts` | attempt no, duration, outcome, safe error code |
| `external_analysis_results` | schema version, structured result, summary, references, result hash, received time |
| `external_analysis_result_references` | document/report revision ve sayfa/bölüm/field locator |
| `external_action_requests` | result, requested command code, target ref, payload hash, status |
| `external_action_executions` | normal application use-case sonucu, actor service, audit/correlation |

İstek akışı:

```text
draft → queued → sent → processing → succeeded
queued/sent/processing → failed → retrying
queued/sent/processing → expired | cancelled
```

İlk capability `report.standard_control` olur. İzin verilebilen aksiyonlar şablon/grant bazında `revision_required`, `notify_employee`, `notify_manager`, `create_follow_up_task` ve `mark_external_check_passed` ile sınırlanır. Yeni capability/aksiyon ayrı onay olmadan eklenmez.

Dış servis:

- DB'ye doğrudan erişmez.
- User/Personel gibi gösterilmez.
- Kendisine tanımlanmamış alan veya dosyayı alamaz.
- Idempotency, Policy, transition, approval ve audit'i atlayamaz.
- Local prompt, model ayarı, RAG, embedding, vector, training data veya AI memory tablosu oluşturulmasına gerekçe olamaz.

## 14. Workflow, audit ve entegrasyon

- `workflow_definitions`, `workflow_definition_versions`, `workflow_instances`, `workflow_steps`, `workflow_tasks`.
- `approval_policies`, `approval_policy_versions`, `approval_steps`, `approval_requests`, `approval_request_steps`, `approval_decisions`, `delegation_snapshots`.
- `personnel_activities`: actor, correlation, object, action, redacted delta/hash, channel, time.

Workflow instance başladığı definition/policy sürümüne sabitlenir. `approval_policies` stable kök, `approval_policy_versions` immutable kural sürümüdür. `approval_steps` policy tanımıdır; `approval_request_steps` belirli bir request için çözümlenmiş approver/rol snapshot'ıdır. Ortak `approval_requests`/`approval_request_steps`/`approval_decisions` seti İş Alım, sözleşme, devir, proje gate'i, satın alma, ödeme, DMS ve sosyal içerik tarafından kullanılır; domain bazında duplicate onay tabloları kurulmaz. Kritik onay kararı onaylanan exact record/revision/hash'i taşır.

## 15. Çeviri ve dil modeli

- UI metinleri Laravel translation dosyalarında tutulur.
- TR/EN iş alanlarında basit ve sabit küçük master data için ayrı `_tr/_en` kolonları yalnız gerekçeli ise kullanılabilir.
- Uzun/versiyonlu içerikler için `*_translations` tabloları veya revision'a bağlı dil kayıtları tercih edilir.
- Doküman/rapor şablonu ve sosyal içerik sürümleri dile bağlıdır; aynı kök kaydın TR ve EN varyantları ilişkilidir.
- Her bir TR/EN alanı tek başına opsiyonel olabilir; iş kuralının istediği en az bir dil bulunmalıdır.
- Çıktı dili seçildiğinde o dilin zorunlu alanları eksikse fallback yapılmaz.

## 16. Read model kataloğu

İlk read model'ler:

- `organization_tree_rm`, `personnel_directory_rm`, `personnel_detail_rm`.
- `personnel_reporting_inbox_rm`, `manager_report_review_rm`, `report_compliance_rm`, `company_reporting_summary_rm`.
- `notification_inbox_rm`, `critical_alert_rm`.
- `document_register_rm`, `document_acknowledgement_rm`, `conversation_list_rm`.
- `functional_area_responsibility_rm`, `social_account_health_rm`, `social_content_calendar_rm`, `social_special_day_readiness_rm`, `social_publication_performance_rm`.
- `business_case_pipeline_rm`, `business_case_lineage_rm`.
- `project_portfolio_rm`, `project_workstream_board_rm`, `project_gate_readiness_rm`.
- `external_analysis_status_rm`.

`project_portfolio_rm` hem kart hem tablo görünümüne şu sözleşmeyi verir: TKLF/PRJ kodları, müşteri, ülke, yönetici, genel durum, macro gate, primary focus, altı workstream durumu, plan/gerçekleşen ilerleme, gecikme, açık issue/risk, bekleyen onay/doküman ve son güncelleme.

Başlangıçta Query Service veya basit sorgular için normal MySQL view kullanılır. Hacim gerektirirse idempotent beslenen fiziksel projection/read-model tablosu eklenir; MySQL'de native materialized view varsayılmaz. Projection satırı `last_projected_event_id` ve `projected_at` taşır; dashboard gerekli yerde eventual consistency zamanını gösterir.

## 17. Kritik constraints ve indeksler

- Normalize edilmiş iş e-postasında unique index.
- Aynı anda tek primary position assignment ve tek line manager için kök satır kilidi + indeksli overlap kontrolü; satır içi tarih sırası için `CHECK`.
- Report schedule+period ve period+personnel unique.
- Geciken assignment, manager review ve notification acknowledgment sorguları için `(status, due_at, reviewer_id/recipient_id)` biçiminde sorguya özel composite indeksler; gerekirse indexed generated `is_actionable` alanı.
- Document number ve document+revision unique.
- Conversation+sequence unique ve chronological index.
- Business allocation sequence unique; business code için `(kind, sequence_no)` ve generated formatted code unique; TKLF/PRJ sequence eşliğini koruyan composite FK.
- Business case başına tek project.
- Project başına tek açık primary focus: `ended_at IS NULL` iken project kimliğini, aksi durumda `NULL` üreten STORED generated guard üzerinde unique index.
- Workstream dependency self-reference CHECK ve duplicate unique; graph cycle servis doğrulaması.
- External request idempotency key unique; request başına tek seçili sonuç nullable generated guard + unique index ile; action execution idempotency unique.
- Outbox/inbox external message kimlikleri unique.
- Functional Area ve sosyal medya için 4.3 ile 9.5'teki tek-owner, hesap, bağlantı, içerik sürümü, yayın, reminder ve metric benzersizlikleri fiziksel indeks kataloğuna zorunlu girer.
- Append-only event tabloları ile mutable teslim/dispatch durumları ayrı tutulur. Append-only tablolarda runtime uygulama rolünün UPDATE/DELETE yetkisi physical design kapısında kaldırılır; deploy/DBA hesabı ayrıdır.
- Partition MVP'de kullanılmaz. FK'li operasyonel tablolar partition edilmez; retention/archive ve indeksleme önce uygulanır. Yalnız FK'siz, çok yüksek hacimli event tabloları için MySQL unique-key/partition-key ve foreign-key kısıtları DBA tarafından ayrıca incelenir.

## 18. DB tasarım kapıları

### DB-G0 — Terim ve kaynak izlenebilirliği

Personel/User/Position/OrgUnit/FunctionalArea, report/submission/review, notification/acknowledgment, Sosyal Medya, İş Alım/Operasyon ve dış servis sınırları onaylanır.

### DB-G1 — Personel ve erişim

- Gerçek organizasyon ağacı, pozisyonlar, matrix reporting ve vekâlet.
- Aktif personel hesabı, hassas alan ve retention matrisi.
- Personel kartı/read model alanları ve negatif erişim senaryoları.

### DB-G2 — Raporlama ve bildirim

- Bir gerçek günlük ve bir gerçek haftalık şablon.
- Hedef resolver, deadline, grace, reviewer, reminder ve escalation.
- Alan tipleri, PDF dili, dış kontrol modu ve action allowlist.

### DB-G3 — DMS, chat ve e-posta

- Doküman numarası/revizyon, dosya tip/boyut, saklama/legal hold.
- Chat geçmiş görünürlüğü, redaction ve özel konuşma politikası.
- Kurumsal mailbox sağlayıcısı ve duplicate/retry kuralı.

### DB-GSM — Functional Area ve Sosyal Medya

- Bugünkü team/personel sahipliği, functional-area rolleri ve ileride efektif tarihli OrgUnit/departman bağlama prosedürü.
- Resmî platform/hesap kataloğu, normalized handle/external ID ve manuel/connected çalışma modu.
- Her hesap için profile/metrics/publish/schedule/webhook capability ve izin scope matrisi.
- Secret manager referansı, OAuth expiry, webhook doğrulama, retry/idempotency ve raw payload retention.
- İçerik kökü, TR/EN immutable version, DMS asset, exact-version onayı, target ve publication durum makineleri.
- Özel gün kaynağı/occurrence doğrulaması, timezone, T−4/T−3 policy step'leri, readiness ve recipient resolver.
- Canonical metric/KPI sözlüğü, provider mapping sürümleri, observation periyotları ve manuel veri kanıtı.
- Hesap kapatma/devir, yayımlanmış içerik düzeltme/silme kanıtı ve sosyal medya retention politikası.

### DB-G4 — İş kodu ve Operasyon devri

- Global sequence ve `TKLF-n → PRJ-n` eşliği.
- Bir business case → bir project varsayımı.
- Kayıp/iptal numaralarının yeniden kullanılmaması.
- Handoff teslimatları, kabul eden ve atomik dönüşüm.

### DB-G5 — Operasyon

- Altı grup kodu, primary focus yetkisi, dependency ve waiver.
- Proje tipi/component kataloğu, macro gate, evidence ve onay sahipleri.
- Satın alma-finans-lojistik-saha-teknik-test cardinality'leri.

### DB-G6 — Harici servis

- Endpoint/auth/callback sözleşmesi.
- Dışarı gönderilebilir alan sınıfları.
- Sonuç şeması, saklanacak minimum metadata, timeout/retry.
- Capability ve otomatik action allowlist'i.

### DB-G7 — MySQL fiziksel tasarım

- MySQL 8.4 LTS exact patch/managed hosting, InnoDB parametreleri ve geliştirme-staging-production sürüm eşliği.
- `utf8mb4` collation, normalized e-posta/kod benzersizliği, UTC connection timezone ve strict SQL mode.
- AUTO_INCREMENT `BIGINT UNSIGNED` cast/route/log standardı; foreign key tip eşliği.
- Composite/generated-column indeks kataloğu, overlap kilit sırası, deadlock retry ve execution-plan eşikleri.
- MVP'de partition kullanılmaması; ileride yalnız uygun event tabloları için MySQL partition/FK/unique-key kısıt incelemesi.
- Redis, object storage, encryption/key, backup/PITR, restore provası ve veri yerleşimi.
- InnoDB `FULLTEXT` TR/EN kalite deneyi ve gerekirse ayrı arama servisi karar kriteri.
- Temel spatial gereksinim varsa SRID/geometri kataloğu; ileri GIS servis sınırı.

### DB-G8 — Model dondurma

Onaylanacak paket:

- [Tam ERD](05-veri-tabani-er-diyagramlari.md) ve data dictionary.
- Cardinality/constraint matrisi.
- Durum makineleri ve event kataloğu.
- Read model/API sözleşmeleri.
- Security/retention ve legacy/import eşlemeleri.
- Migration üretim sırası ve DBA teslim checklist'i.

DB-G8, DB-GSM dahil önceki kapıların tamamını dondurur. DB-G8 tamamlanmadan migration yazılmaz; sonrasında Codex migration çalıştırmaz.

0.7 sürümünde bu paketin taslakları üretilmiştir: veri sözlüğü [06](06-veri-sozlugu-01-cekirdek-ve-personel.md)–[12](12-veri-sozlugu-07-workflow-audit-entegrasyon.md), [cardinality/constraint matrisi](13-cardinality-ve-constraint-matrisi.md), [durum makineleri ve olay kataloğu](14-durum-makineleri-ve-olay-katalogu.md), [güvenlik/saklama matrisi](15-guvenlik-siniflandirma-ve-saklama-matrisi.md), [migration sırası ve DBA teslim paketi](16-migration-uretim-sirasi-ve-dba-teslim-paketi.md). Onay ifadesi ve karar defteri [17](17-db-g8-onay-paketi-ve-karar-defteri.md) belgesindedir; kapı henüz kapanmamıştır.

## 19. Açık karar kayıtları

Aşağıdakiler bir sonraki planlama turlarında gerçek şirket verisiyle kapatılacaktır:

1. Organizasyon birimleri, pozisyonlar, tüm manager ilişkileri ve ilk personel kartı alanları.
2. Günlük/haftalık ilk şablonlar, teslim saatleri, tatiller, reviewer ve escalation süreleri.
3. Rapor dış kontrolünün hangi şablonlarda öneri, revizyon zorunlu veya otomatik bildirim/görev modunda olacağı.
4. Chat saklama, düzenleme/redaction ve ayrılan personelin geçmiş erişimi.
5. PDF motoru, print CSS onayı, kurumsal logo/font ve TR/EN örnek çıktı.
6. Kurumsal e-posta sağlayıcısı ve teklif mailbox bağlantı yöntemi.
7. Operasyona devirde sözleşme/LOI/NTP zorunluluğu ve kritik proje eşikleri.
8. Bir `TKLF-n` kaydından gelecekte birden fazla proje çıkıp çıkamayacağı.
9. Proje tipi/gate şablonları, altı workstream'in başlangıç bağımlılıkları ve waiver yetkilisi.
10. Yazılım Grubunun kesin alt disiplinleri ile üç test aşamasının kurumsal ad/sahipleri.
11. Zirve, MS Project, dosya sunucusu, ihale kaynakları ve SCADA entegrasyon sözleşmeleri.
12. Üretim hacmi: aktif kullanıcı, eşzamanlı kullanıcı, yıllık rapor/doküman, dosya boyutu ve aktif proje sayısı.
13. MySQL managed hosting, exact 8.4 patch, HA/failover, backup/PITR hedefleri, connection kapasitesi ve kabul edilen RPO/RTO.
14. `utf8mb4` collation kararı ile Türkçe `I/İ/ı/i`, aksan, e-posta, kod ve firma adı benzersizlik örnekleri.
15. Resmî sosyal platform/hesap listesi, doğrulanmış URL/external ID, hesap sahipleri ve mevcut admin'ler.
16. Functional Area ilk RACI'si, maker-checker ayrılığı ve departmanlaşma karar/geçiş yetkilisi.
17. Sosyal içerik türleri, marka dili, yasak/kısıtlı konular, kamuya açıklama ve kriz onay politikası.
18. TR/EN içerik zorunluluğunun hesap/içerik türü matrisi ile DMS telif/rıza kanıtları.
19. Özel gün ana kataloğu, authoritative kaynak, ülke/bölge, T−4/T−3 gönderim saati ve hafta sonu/tatil davranışı.
20. İlk canonical KPI sözlüğü, platform metric mapping/formül/sampling sıklığı ve manuel veri kabul kanıtı.
21. Platform bazında read-only metric veya publish beklentisi; API ücret/rate-limit/scope/webhook şartları ve secret manager sahibi.
22. Draft, varlık, raw provider payload, webhook, metric ve yayımlanmış içerik düzeltme/silme retention politikası.

Bu kararlar kapanmadan bu belge `migration-ready` durumuna yükseltilmez. Her maddenin öneri değeri ve durumu [karar defterinde](17-db-g8-onay-paketi-ve-karar-defteri.md) (D-01…D-41) izlenir.

## 20. Resmî MySQL teknik referansları

- [MySQL 8.4 Reference Manual](https://dev.mysql.com/doc/refman/8.4/en/)
- [CHECK constraint kuralları](https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html)
- [Generated column tanımı ve kısıtları](https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html)
- [Native JSON tipi ve indeksleme yaklaşımı](https://dev.mysql.com/doc/refman/8.4/en/json.html)
- [DATE, DATETIME ve TIMESTAMP davranışı](https://dev.mysql.com/doc/refman/8.4/en/datetime.html)
- [InnoDB FULLTEXT araması](https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html)
- [Spatial index optimizasyonu](https://dev.mysql.com/doc/refman/8.4/en/spatial-index-optimization.html)
- [Partition ve InnoDB foreign key kısıtları](https://dev.mysql.com/doc/refman/8.4/en/partitioning-limitations-storage-engines.html)
