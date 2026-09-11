# Konelsis Kurumsal Platform — Veri sözlüğü 1: sözleşme, çekirdek, kimlik, organizasyon, personel ve İK

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. `personnel` tablosu hem çalışan kartı hem giriş hesabıdır (D-42).  
**Sürüm:** 0.8 / 5 Eylül 2026  
**Kaynak model:** [Veri tabanı tasarım planı](03-veri-tabani-tasarim-plani.md) ve [ER diyagramları](05-veri-tabani-er-diyagramlari.md)  
**Hedef veritabanı:** MySQL 8.4 LTS / InnoDB / `utf8mb4`

Veri sözlüğü yedi bölümdür; bu bölüm bütün bölümlerin ortak sözleşmesini (bölüm 1) taşır:

| Bölüm | Kapsam | ERD |
|---|---|---|
| [06 — Çekirdek ve personel](06-veri-sozlugu-01-cekirdek-ve-personel.md) | Sözleşme, referans, kimlik, organizasyon, personel, Functional Area, İK | ERD-01, ERD-02 |
| [07 — Raporlama ve bildirim](07-veri-sozlugu-02-raporlama-ve-bildirim.md) | Şablonlu raporlama, bildirim, kritik iş, görev | ERD-03, ERD-04 |
| [08 — DMS ve iletişim](08-veri-sozlugu-03-dms-iletisim-eposta.md) | Dosya, doküman, PDF çıktısı, konuşma, e-posta alımı | ERD-05 |
| [09 — Sosyal medya](09-veri-sozlugu-04-sosyal-medya.md) | Hesap, bağlantı, içerik, özel gün, metrik/KPI | ERD-06 |
| [10 — Party ve İş Alım](10-veri-sozlugu-05-party-ve-is-alim.md) | Party, business case/kod, ihale, teklif, sözleşme, devir | ERD-07 |
| [11 — Operasyon](11-veri-sozlugu-06-operasyon.md) | Proje, workstream, gate, satın alma, lojistik, finans, teknik, saha, kalite, test, servis | ERD-08, ERD-09, ERD-10 |
| [12 — Workflow ve entegrasyon](12-veri-sozlugu-07-workflow-audit-entegrasyon.md) | Workflow, onay, Personel Hareketleri, entegrasyon, harici analiz | ERD-11 |

Sözlük yalnız kolon/tip/kural tanımlar. İsimlendirilmiş constraint ve indeks kataloğu [13 — Cardinality ve constraint matrisi](13-cardinality-ve-constraint-matrisi.md), durum makineleri [14](14-durum-makineleri-ve-olay-katalogu.md), veri sınıfı/saklama [15](15-guvenlik-siniflandirma-ve-saklama-matrisi.md), migration sırası [16](16-migration-uretim-sirasi-ve-dba-teslim-paketi.md) ve onay/karar defteri [17](17-db-g8-onay-paketi-ve-karar-defteri.md) belgesindedir.

## 1. Ortak sözleşme

### 1.1 Genel kurallar

- Tablo ve kolon adları lowercase `snake_case`, tablo adları çoğuldur. MySQL tanımlayıcı sınırı 64 karakterdir; constraint adları için [13](13-cardinality-ve-constraint-matrisi.md) belgesindeki kısaltma kaydı kullanılır.
- Bütün tablolar `ENGINE=InnoDB`, `ROW_FORMAT=DYNAMIC`, `CHARACTER SET utf8mb4` ile oluşturulur. Varsayılan collation ve Türkçe sıralama kararı DB-G7'de dondurulur; bu sözlükteki öneri bölüm 1.2'de belirtilmiştir.
- Teknik kimlik otomatik artan sayısal kimliktir ve `BIGINT UNSIGNED` saklanır. Tek istisna `countries.code`, `currencies.code` gibi ISO doğal anahtarlar ile `business_number_allocations.sequence_no`'dur.
- İş durumu kolonları `VARCHAR(32)` + isimlendirilmiş `CHECK`'tir; MySQL native `ENUM` ve `SET` kullanılmaz. Her durum kolonunun izinli değerleri bu sözlükte köşeli parantez içinde listelenir ve PHP backed enum ile bire bir eşlenir.
- Çok değerli çekirdek alanlar çocuk tablodur. `JSON` yalnız `_config`, `_snapshot`, `_payload`, `_delta` sonekli alanlarda bulunur; sorgulanacak JSON scalar değeri generated column'a çıkarılır.
- Foreign key silme davranışı varsayılan `ON DELETE RESTRICT ON UPDATE RESTRICT`'tir. `CASCADE` yalnız saf kompozisyon çocuklarında (`*_translations`, `*_weekdays`, `*_options` gibi) ve yalnız [13](13-cardinality-ve-constraint-matrisi.md) belgesinde açıkça işaretlenmişse kullanılır.
- Kritik kayıtlarda hard delete yoktur; `status` veya `archived_at` ile kapatılır. Laravel `SoftDeletes` (`deleted_at`) kullanılmaz.
- Immutable sürüm satırları yayımlandıktan/onaylandıktan sonra içerik kolonlarında değiştirilmez; yalnız yaşam döngüsü kolonları (`status`, `superseded_at`, `superseded_by_*`) uygulama servisi tarafından güncellenir.
- Bütün anlık zaman kolonları UTC `DATETIME(6)`; yerel iş tarihi `DATE` + IANA `timezone` kolonu ile birlikte tutulur.

### 1.2 Fiziksel tip takma adları

Sözlükte kolon tipleri aşağıdaki takma adlarla yazılır; DDL üretiminde bire bir bu fiziksel tiplere açılır.

| Takma ad | MySQL 8.4 fiziksel tip | Not |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Birincil anahtar; klasik otomatik artan sayı (karar D-15R) |
| `fk` | `BIGINT UNSIGNED` | Başka tablonun `id` kolonuna işaret eder |
| `ts` | `DATETIME(6)` | UTC; `DEFAULT CURRENT_TIMESTAMP(6)` yalnız `created_at`/`updated_at`'ta |
| `date` | `DATE` | Yerel iş günü; ilgili satırda `timezone` bulunur |
| `time` | `TIME(0)` | Yerel saat (policy/gönderim saati) |
| `tz` | `VARCHAR(64) CHARACTER SET ascii` | IANA timezone kimliği, örn. `Europe/Istanbul` |
| `code` | `VARCHAR(64)` | Normalize kod; DB-G7 önerisi `COLLATE utf8mb4_0900_as_cs` |
| `code32` | `VARCHAR(32)` | Kısa kod/enum dışı sabit |
| `short` | `VARCHAR(100)` | Kısa metin |
| `name` | `VARCHAR(255)` | Ad/başlık |
| `text` | `TEXT` | ≤ 64 KB açıklama |
| `longtext` | `MEDIUMTEXT` | ≤ 16 MB; yalnız içerik gövdesi/snapshot |
| `status` | `VARCHAR(32)` | + isimlendirilmiş `CHECK` |
| `bool` | `TINYINT(1)` | 0/1, `NOT NULL DEFAULT 0` |
| `tint` | `TINYINT UNSIGNED` | 0–255 |
| `sint` | `SMALLINT` | |
| `int` | `INT` | |
| `bigu` | `BIGINT UNSIGNED` | Sıra numarası, row_version, byte |
| `money` | `DECIMAL(20,4)` | Daima `currency_code` ile |
| `qty` | `DECIMAL(18,6)` | Daima `uom_id` ile |
| `pct` | `DECIMAL(7,4)` | 0–100 aralığı; `CHECK` ile |
| `ratio` | `DECIMAL(9,6)` | 0–1 aralığı |
| `hash` | `VARCHAR(64)` | İçerik özeti (SHA-256, onaltılık) |
| `key32` | `VARCHAR(64)` | SHA-256 idempotency/dedupe anahtarı |
| `json` | `JSON` | |
| `email` | `VARCHAR(320)` | Ham değer; benzersizlik `normalized_*` kolonunda |
| `url` | `VARCHAR(2048)` | Benzersizlik gerekiyorsa yanına `url_hash` (`hash`) |
| `locale` | `VARCHAR(10) CHARACTER SET ascii` | İlk sürüm `tr`, `en` |
| `country` | `CHAR(2) CHARACTER SET ascii` | ISO 3166-1 alpha-2 |
| `currency` | `CHAR(3) CHARACTER SET ascii` | ISO 4217 |
| `enc` | `VARBINARY(n)` | Uygulama tarafında şifrelenmiş alan; `n` tabloda verilir |

DB-G7 collation önerisi: veritabanı varsayılanı `utf8mb4_0900_ai_ci`; kişi/firma adı gibi Türkçe sıralanacak kolonlarda `utf8mb4_tr_0900_ai_ci`; kod, handle, normalize e-posta ve `formatted_code` gibi benzersiz kimlik kolonlarında `utf8mb4_0900_as_cs`. `I/İ/ı/i` örnek seti [17](17-db-g8-onay-paketi-ve-karar-defteri.md) belgesindeki D-07 kararında test edilecektir.

### 1.3 Standart kolon setleri

Her tablo başlığında hangi setlerin uygulandığı `+S1 +S2` biçiminde yazılır; bu kolonlar tablo listelerinde tekrar edilmez.

| Set | Kolonlar | Kural |
|---|---|---|
| **S0** | `id` (`BIGINT UNSIGNED AUTO_INCREMENT`) | Bütün tablolarda; doğal anahtarlı istisnalar başlıkta belirtilir |
| **S1 — oluşturma izi** | `created_at ts NOT NULL DEFAULT CURRENT_TIMESTAMP(6)`, `created_by_personnel_id fk NULL → personnel` | Kaydı sistem oluşturduysa personel alanı NULL kalır ve arayüzde "Sistem" gösterilir |
| **S2 — güncelleme izi** | `updated_at ts NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)`, `updated_by_personnel_id fk NULL → personnel`, `row_version bigu NOT NULL DEFAULT 1` | Optimistic locking: uygulama servisi `row_version` eşitliğiyle günceller |
| **S3 — arşiv** | `archived_at ts NULL`, `archived_by_personnel_id fk NULL → personnel`, `archive_reason short NULL` | Hard delete yerine |

Tablo sınıfları:

| Sınıf | Anlam | Runtime DB rolü yetkisi |
|---|---|---|
| **A** | Append-only olay/geçmiş tablosu; S1 var, S2 yok | Yalnız `INSERT`/`SELECT`; `UPDATE`/`DELETE` DB-G7'de kaldırılır |
| **V** | Sürüm satırı; yayım/onay sonrası içerik immutable | `UPDATE` yalnız yaşam döngüsü kolonlarında; servis invariant'ı |
| **M** | Mutable ana veri/taslak/iş kaydı | Normal |
| **R** | Referans/lookup ana verisi | Normal; yalnız admin use-case'leri yazar |
| **F** | Laravel/Filament framework tablosu | Framework şeması korunur |
| **P** | Projection/read model (`_rm`) | Yalnız projector yazar; sözlük kapsamı dışında |

Veri sınıfı kısaltmaları (bkz. [15](15-guvenlik-siniflandirma-ve-saklama-matrisi.md)): **P** public, **I** internal, **C** confidential, **R** restricted.

### 1.4 Tekrarlanan fiziksel desenler

- **Tarih aralığı satırı:** `valid_from` NOT NULL, `valid_until` NULL; `CHECK (valid_until IS NULL OR valid_until > valid_from)`. Aynı kapsamda overlap yasağı 03 §5.2 kilit deseniyle uygulama servisinde; DB tarafında composite index `(kök_id, valid_from, valid_until)`.
- **Generated guard (tek aktif satır):** `STORED` generated kolon koşul doğruyken kök kimliği, değilse `NULL` üretir; üzerinde `UNIQUE`. Örnek: `active_owner_guard = CASE WHEN role_code = 'function_owner' AND is_primary = 1 AND valid_until IS NULL THEN functional_area_id ELSE NULL END`.
- **XOR hedef:** `CHECK ((a_id IS NOT NULL) + (b_id IS NOT NULL) + (c_id IS NOT NULL) = 1)`.
- **Self-link yasağı:** `CHECK (parent_id <> child_id)` veya `CHECK (parent_id IS NULL OR parent_id <> id)`.
- **Sürüm zinciri:** kök tablo `current_version_id fk NULL`; sürüm tablosu `(kök_id, version_no)` UNIQUE; kökün `current_version_id` yalnız aynı kökün sürümünü gösterir (composite FK, bkz. 13 §2).
- **Aynı aggregate garantisi:** çocuk satırın aynı projeye/konuşmaya ait olması gereken yerde `(project_id, id)` composite unique + composite FK.
- **Kontrollü genel referans:** `target_type code32` + `target_id` (metin olarak saklanan sayısal kimlik); izinli `target_type` değerleri [12](12-veri-sozlugu-07-workflow-audit-entegrasyon.md) belgesindeki `reference_type_registry` ile sınırlıdır ve `CHECK` listesi migration'da bu registry'den üretilir.

### 1.5 ERD–plan uyum notları

Sözlük hazırlanırken tespit edilen ve [17](17-db-g8-onay-paketi-ve-karar-defteri.md) karar defterine taşınan tutarsızlıklar:

| Not | Konu | Sözlükteki çözüm | Karar |
|---|---|---|---|
| N-01 | Laravel/Filament veritabanı bildirim kanalı fiziksel `notifications` tablosunu kullanır; ERD-04 aynı adı iş bildirimi kökü için kullanır | İş kökü `notification_instances` olarak yazıldı; `notifications` framework tablosu olarak ayrıldı | D-12 |
| N-02 | ERD-02 `functional_area_role_definitions.capability_set` JSON dizi; 03 §2 çok değerli çekirdek alanda JSON yasaklar | `functional_area_role_capabilities` çocuk tablosu eklendi | D-13 |
| N-03 | ERD-02 `leave_approvals`/`expense_approvals`; 03 §14 domain bazlı ikinci onay tablosunu yasaklar | Ortak `approval_requests` kullanıldı; iki tablo "kaldırılması önerilen" olarak listelendi | D-14 |
| N-04 | Tüm kimlikler klasik `BIGINT UNSIGNED AUTO_INCREMENT`'tir; önceki UUID kararı geri alınmıştır | Sözlük ve migration'lar sayısal kimlik kullanır | D-15R |
| N-05 | ERD'de RBAC (rol/izin) tablosu yok; 01 §7 Filament Shield/Spatie Permission'ı uygulama aşamasına erteler | Ayrılmış tablo adları bölüm 3.4'te; şema kararı D-16 | D-16 |
| N-06 | `business_number_allocations.status` planda var; aynı transaction içinde ayrılıp kullanıldığı için pratikte tek değerlidir | Bilgi amaçlı `status` korundu, A sınıfı | — |
| N-07 | 03 §5.4 `attendance_entries` ve `resource_assignments` ERD-02'de çizilmemiş | Sözlüğe eklendi | — |
| N-08 | 03 §4.1 `units_of_measure`, `business_calendars` haftalık çalışma günü listesi | `business_calendar_weekdays` çocuk tablosu eklendi | — |
| N-09 | `service_accounts`, `outbox_messages`, `outbox_deliveries` ve `status_changes` tabloları modelden çıkarıldı | Bütün belgelerde bu adlar **ertelenmiş** sayılır; olay ve durum geçmişinin tek kaynağı `personnel_activities`'tir. Ertelenen başlıklar [02 §11](02-modul-bazli-ilerleme-plani.md) listesindedir. Bu tablolara atıf yapan eski bölümler yeniden yetkilendirilmeden uygulanmaz. | D-47, D-48 |
| N-10 | MySQL 8, `CHECK` ifadesinde `AUTO_INCREMENT` kolonuna atıf yapılmasına izin vermez | "Kendi kendine bağlanma" kuralları (`departments.parent_id`, `units_of_measure.base_unit_id`) veritabanı `CHECK`'i yerine uygulama servisinde uygulanır | — |

## 2. Referans ve organizasyon çekirdeği (ERD-01)

### 2.1 `organizations` — R, sınıf I, +S1 +S2

Tek Konelsis üst organizasyonu. İlk sürümde tek satır; SaaS tenant değildir.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE; `KONELSIS` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `default_locale` | locale | ✗ | `tr` |
| `default_timezone` | tz | ✗ | `Europe/Istanbul` |
| `default_currency_code` | currency | ✗ | → `currencies` |
| `status` | status | ✗ | [`active`, `inactive`] |

### 2.2 `countries` — R, sınıf P, +S1 +S2 (PK `code`)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | country | ✗ | PRIMARY KEY; ISO 3166-1 alpha-2 |
| `iso3_code` | `CHAR(3) ascii` | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `default_timezone` | tz | ✓ | Çok saat dilimli ülkelerde NULL |
| `status` | status | ✗ | [`active`, `inactive`] |

### 2.3 `currencies` — R, sınıf P, +S1 +S2 (PK `code`)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | currency | ✗ | PRIMARY KEY; ISO 4217 |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `decimal_places` | tint | ✗ | `CHECK (decimal_places <= 6)` |
| `status` | status | ✗ | [`active`, `inactive`] |

### 2.4 `units_of_measure` — R, sınıf P, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `dimension` | code32 | ✗ | [`count`, `length`, `area`, `volume`, `mass`, `time`, `energy`, `power`, `voltage`, `current`, `temperature`, `other`] |
| `code` | code32 | ✗ | UNIQUE `(dimension, code)`; örn. `MWp`, `m`, `pcs` |
| `symbol` | code32 | ✗ | |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `base_unit_id` | fk | ✓ | → `units_of_measure` (self); NULL ise taban birim |
| `to_base_factor` | `DECIMAL(24,12)` | ✓ | `base_unit_id` doluysa zorunlu; `CHECK (to_base_factor > 0)` |
| `status` | status | ✗ | [`active`, `inactive`] |

### 2.5 `legal_entities` — M, sınıf I, +S1 +S2 +S3

Hukuk/finans tarafından doğrulanan tüzel kişilikler. İlk sürüm ana Konelsis AŞ.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `organization_id` | fk | ✗ | → `organizations` |
| `code` | code | ✗ | UNIQUE `(organization_id, code)` |
| `legal_name` | name | ✗ | Resmî unvan |
| `short_name` | short | ✗ | |
| `country_code` | country | ✗ | → `countries` |
| `currency_code` | currency | ✗ | → `currencies`; raporlama para birimi |
| `timezone` | tz | ✗ | |
| `tax_number` | code32 | ✓ | Vergi/VAT no; ülke bazlı format servis doğrulaması |
| `registration_number` | code | ✓ | Ticaret sicil / şirket kayıt no |
| `entity_kind` | status | ✗ | [`parent`, `subsidiary`, `branch`, `spv`, `jv`] |
| `status` | status | ✗ | [`active`, `inactive`, `dissolved`] |
| `valid_from` | date | ✗ | |
| `valid_until` | date | ✓ | `CHECK` tarih sırası |

### 2.6 `business_calendars` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `legal_entity_id` | fk | ✓ | → `legal_entities`; NULL ise ülke/organizasyon geneli |
| `country_code` | country | ✓ | → `countries` |
| `timezone` | tz | ✗ | |
| `is_default` | bool | ✗ | Generated guard ile legal entity başına tek varsayılan |
| `status` | status | ✗ | [`active`, `inactive`] |

### 2.7 `business_calendar_weekdays` — M, sınıf I, +S1

Haftalık çalışma günlerinin normalize listesi (JSON/SET yerine çocuk tablo).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_calendar_id` | fk | ✗ | → `business_calendars` (CASCADE) |
| `iso_weekday` | tint | ✗ | 1–7; UNIQUE `(business_calendar_id, iso_weekday)` |
| `work_start` | time | ✗ | Yerel |
| `work_end` | time | ✗ | `CHECK (work_end > work_start)` |

### 2.8 `calendar_days` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_calendar_id` | fk | ✗ | → `business_calendars` |
| `local_date` | date | ✗ | UNIQUE `(business_calendar_id, local_date)` |
| `day_kind` | status | ✗ | [`public_holiday`, `religious_holiday`, `company_holiday`, `half_day`, `working_override`] |
| `is_working_day` | bool | ✗ | Haftalık kuralı geçersiz kılar |
| `name_tr` | name | ✓ | |
| `name_en` | name | ✓ | |
| `source_reference` | short | ✓ | Resmî kaynak/karar |

### 2.9 `business_number_allocations` — A, sınıf I (PK `sequence_no`)

TKLF/PRJ için tek global ve geri kullanılmayan sıra. Satır, business case'i oluşturan transaction içinde eklenir; rollback'te oluşan boşluk kabul edilir.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `sequence_no` | bigu | ✗ | PRIMARY KEY, `AUTO_INCREMENT` |
| `purpose` | status | ✗ | [`business_case`] |
| `status` | status | ✗ | [`assigned`, `void`]; bilgi amaçlı, varsayılan `assigned` |
| `allocated_at` | ts | ✗ | |
| `allocated_by_personnel_id` | fk | ✗ | → `personnel` |
| `correlation_id` | fk | ✗ | |

### 2.10 `security_classifications` — R, sınıf P, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code32 | ✗ | UNIQUE; [`public`, `internal`, `confidential`, `restricted`] |
| `rank` | tint | ✗ | UNIQUE; sıralı karşılaştırma için 0–3 |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `description` | text | ✓ | |
| `external_analysis_allowed` | bool | ✗ | Dış AI'ya gönderilebilirlik üst sınırı; `restricted` daima 0 |
| `status` | status | ✗ | [`active`, `inactive`] |

### 2.11 `retention_policies` — R, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `retention_days` | int | ✓ | NULL = süresiz |
| `trigger_kind` | status | ✗ | [`created_at`, `closed_at`, `archived_at`, `separation`, `project_close`, `manual`] |
| `disposition` | status | ✗ | [`purge`, `anonymize`, `cold_archive`, `review`] |
| `legal_basis` | text | ✓ | KVKK/ticari mevzuat dayanağı |
| `status` | status | ✗ | [`active`, `inactive`] |

## 3. Personel ve sistem hesapları (ERD-01, 03 §4.2)

Kullanıcı ve çalışan ayrı kavramlar değildir: bir kişi **personeldir** ve aynı kayıt hem çalışan kartı hem giriş hesabıdır (karar D-42). Otomatik işleri yapan makine kimlikleri ayrı bir tabloda, **sistem hesabı** adıyla tutulur (karar D-43). Önceki tasarımdaki ortak `principals` (aktör) tablosu kaldırılmıştır; kayıtlar doğrudan personele veya sistem hesabına bağlanır.

### 3.1 `personnel` — M, sınıf C, +S1 +S2

Personel kartı ve giriş hesabı. Laravel kimlik doğrulaması bu tabloyu kullanır.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `full_name` | name | ✗ | Ad Soyad |
| `email` | email | ✗ | Ham değer |
| `normalized_email` | email | ✗ | UNIQUE; küçük harfe indirgenmiş |
| `email_verified_at` | ts | ✓ | |
| `password` | `VARCHAR(255)` | ✓ | Hash'lenmiş; NULL yalnız SSO hesabında |
| `remember_token` | `VARCHAR(100)` | ✓ | Laravel |
| `personnel_no` | code32 | ✓ | UNIQUE; sicil numarası. Arayüzde gösterilmez (D-51); kolon ileride kullanılmak üzere durur |
| `national_id` | `VARCHAR(32)` | ✓ | UNIQUE; TC kimlik numarası (sınıf R) |
| `phone` | code32 | ✓ | |
| `job_title` | name | ✓ | Görev |
| `department_id` | fk | ✓ | → `departments` |
| `photo_path` | `VARCHAR(255) ascii` | ✓ | Personel fotoğrafı; `public` diskte saklanır, listede ve detayda yuvarlak avatar olarak gösterilir |
| `hired_on` | date | ✓ | İşe giriş tarihi |
| `locale` | locale | ✗ | Varsayılan `tr`; arayüzde gösterilmez (D-51) |
| `timezone` | tz | ✗ | Varsayılan `Europe/Istanbul`; arayüzde gösterilmez (D-51) |
| `status` | status | ✗ | [`invited`, `active`, `on_leave`, `suspended`, `separated`] |
| `password_changed_at` | ts | ✓ | |
| `last_login_at` | ts | ✓ | |
| `failed_login_count` | tint | ✗ | |

### 3.2 `departments` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code32 | ✗ | UNIQUE; arayüzde sorulmaz, addan otomatik üretilir (D-51) |
| `name` | name | ✗ | |
| `description` | text | ✓ | |
| `parent_id` | fk | ✓ | → `departments`; kendi kendine bağlanma yasağı uygulama servisinde (N-10) |
| `manager_personnel_id` | fk | ✓ | → `personnel` |
| `status` | status | ✗ | [`active`, `inactive`] |

### 3.3 `competencies` — R, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code32 | ✗ | UNIQUE |
| `name` | name | ✗ | |
| `category` | status | ✗ | [`electrical`, `automation`, `software`, `mechanical`, `civil`, `field`, `commercial`, `management`, `safety`, `language`] |
| `description` | text | ✓ | |
| `status` | status | ✗ | [`active`, `inactive`] |

### 3.4 `personnel_competencies` — M, sınıf C, +S1 +S2

Personelin yetkin olduğu konular; arayüzde rozet olarak gösterilir.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` (CASCADE) |
| `competency_id` | fk | ✗ | → `competencies`; UNIQUE `(personnel_id, competency_id)` |
| `level` | status | ✗ | [`beginner`, `intermediate`, `advanced`, `expert`] |
| `note` | short | ✓ | |

### 3.5 `service_accounts` — **ertelendi** (D-48)

Otomatik işleri yapan makine kimliği tablosu kullanıcı kararıyla kapsam dışına alınmıştır. Bu işleri yazılımcı kod, job veya dış yapay zekâ bağlantısı ile yapar ve arayüzde yönetilen bir hesap listesi bulunmaz.

Sonuçları:

- Tablo, model ve ekran yoktur; `personnel_activities` kaydında `service_account_id` kolonu da yoktur.
- Uygulamanın kendi yaptığı işlerde `personnel_activities.personnel_id` boş kalır ve arayüzde "Sistem" gösterilir.
- İhtiyaç doğarsa geri gelir; şartları planın sonundaki [02 §11 E-01](02-modul-bazli-ilerleme-plani.md) maddesindedir.

### 3.6 Ayrılmış RBAC tablo adları (D-16)

Global rol/izin şeması D-16 kararına bağlıdır. Her iki seçenekte de şu adlar ayrılmıştır: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `activity_log`. Spatie `laravel-permission` seçilirse morph anahtar tipi `BIGINT UNSIGNED` olarak yapılandırılır.
## 4. Organizasyon hiyerarşisi ve personel (ERD-01, 03 §5)

### 4.1 `org_units` — M, sınıf I, +S1 +S2 +S3

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `legal_entity_id` | fk | ✗ | → `legal_entities` |
| `code` | code | ✗ | UNIQUE `(legal_entity_id, code)` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `unit_type` | status | ✗ | [`company`, `division`, `department`, `section`, `office`, `site`, `committee`] |
| `cost_center_code` | code32 | ✓ | Muhasebe eşlemesi |
| `status` | status | ✗ | [`planned`, `active`, `inactive`, `dissolved`] |
| `valid_from` | date | ✗ | |
| `valid_until` | date | ✓ | `CHECK` tarih sırası |

### 4.2 `org_unit_relations` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `parent_org_unit_id` | fk | ✗ | → `org_units` |
| `child_org_unit_id` | fk | ✗ | → `org_units`; `CHECK (parent <> child)` |
| `relation_type` | status | ✗ | [`hierarchy`, `functional`, `matrix`] |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |

Aynı `child + relation_type` için aktif overlap yasağı ve döngü kontrolü uygulama servisinde; `hierarchy` tipinde aynı anda tek parent generated guard ile korunur (13 §2).

### 4.3 `positions` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `org_unit_id` | fk | ✗ | → `org_units` |
| `code` | code | ✗ | UNIQUE `(org_unit_id, code)` |
| `title_tr` | name | ✗ | |
| `title_en` | name | ✗ | |
| `grade` | code32 | ✓ | |
| `managerial_level` | tint | ✗ | 0 = yönetici değil; `CHECK (<= 5)` |
| `headcount` | sint | ✗ | `CHECK (headcount >= 0)` |
| `status` | status | ✗ | [`active`, `frozen`, `closed`] |
| `valid_from` | date | ✗ | |
| `valid_until` | date | ✓ | `CHECK` tarih sırası |

### 4.4 Ayrı çalışan tablosu — kaldırıldı

Önceki tasarımda çalışan kartı ile giriş hesabı iki ayrı tablodaydı. Karar D-42 ile tek kayıtta birleştirildi: **`personnel`**. Alanlar için bkz. [§3.1 `personnel`](#31-personnel--m-sınıf-c-s1-s2). Departman bağı `personnel.department_id`, yetkinlikler `personnel_competencies` üzerinden tutulur.

Bütün belgelerde `personnel_id` alanları `personnel` tablosuna işaret eder; `employee_id` ve `user_id` adları sözlükten çıkmıştır (framework'ün `sessions.user_id` kolonu hariç).

### 4.5 `personnel_private_profiles` — M, sınıf R, +S1 +S2 (PK `personnel_id`)

Genel personel kartı ve read model dışında; dar HR Policy. Şifreleme uygulama katmanında, anahtar sürümü satırda.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | PRIMARY KEY; → `personnel` |
| `national_id_enc` | enc(512) | ✓ | TC kimlik/pasaport |
| `national_id_hash` | key32 | ✓ | UNIQUE; tekilleştirme için HMAC |
| `birth_date_enc` | enc(64) | ✓ | |
| `bank_account_enc` | enc(512) | ✓ | IBAN |
| `emergency_contact_enc` | enc(1024) | ✓ | |
| `health_notes_enc` | enc(4096) | ✓ | |
| `encryption_key_version` | sint | ✗ | Key rotation |
| `last_verified_at` | ts | ✓ | HR doğrulama |

### 4.6 `employments` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `legal_entity_id` | fk | ✗ | → `legal_entities` |
| `employment_type` | status | ✗ | [`permanent`, `fixed_term`, `contractor`, `intern`, `secondment`] |
| `contract_reference` | code | ✓ | Sözleşme no; belge DMS'de |
| `status` | status | ✗ | [`planned`, `active`, `ended`] |
| `end_reason` | code32 | ✓ | |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |

### 4.7 `position_assignments` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `position_id` | fk | ✗ | → `positions` |
| `is_primary` | bool | ✗ | |
| `allocation_pct` | `DECIMAL(5,2)` | ✗ | `CHECK (> 0 AND <= 100)`; toplam ≤ 100 servis kontrolü |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |
| `primary_active_guard` | fk (generated) | ✓ | `CASE WHEN is_primary = 1 AND valid_until IS NULL THEN personnel_id END`; UNIQUE |

### 4.8 `reporting_relationships` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `manager_employee_id` | fk | ✗ | → `personnel`; `CHECK (<> personnel_id)` |
| `relation_type` | status | ✗ | [`line`, `functional`, `project`] |
| `scope_type` | status | ✗ | [`all`, `org_unit`, `project`, `functional_area`] |
| `scope_id` | fk | ✓ | `scope_type = all` ise NULL |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |
| `line_active_guard` | fk (generated) | ✓ | `CASE WHEN relation_type = 'line' AND valid_until IS NULL THEN personnel_id END`; UNIQUE (tek aktif doğrudan amir) |

Manager zinciri döngüsü uygulama servisinde reddedilir.

### 4.9 `teams` — M, sınıf I, +S1 +S2 +S3

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `team_type` | status | ✗ | [`functional`, `project`, `task_force`, `committee`] |
| `owner_org_unit_id` | fk | ✓ | → `org_units` |
| `project_id` | fk | ✓ | → `projects`; yalnız `team_type = project` |
| `status` | status | ✗ | [`active`, `inactive`, `dissolved`] |

### 4.10 `team_memberships` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `team_id` | fk | ✗ | → `teams` |
| `personnel_id` | fk | ✗ | → `personnel` |
| `role_code` | code32 | ✗ | [`lead`, `deputy`, `member`] |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |

### 4.11 `delegations` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `grantor_employee_id` | fk | ✗ | → `personnel` |
| `delegate_employee_id` | fk | ✗ | → `personnel`; `CHECK (<> grantor)` |
| `capability_code` | code | ✗ | Yetki kataloğu kodu (`approval.*`, `report.review`, `functional_area.*` …) |
| `scope_type` | status | ✗ | [`all`, `org_unit`, `project`, `functional_area`, `approval_policy`] |
| `scope_id` | fk | ✓ | |
| `reason` | text | ✗ | |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `status` | status | ✗ | [`pending`, `active`, `revoked`, `expired`] |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✗ | Zorunlu bitiş; `CHECK (valid_until > valid_from)` |
| `revoked_at` | ts | ✓ | |
| `revoked_by_personnel_id` | fk | ✓ | → `personnel` |
| `revoke_reason` | text | ✓ | |

### 4.12 `personnel_status_histories` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `from_status` | status | ✓ | İlk kayıtta NULL |
| `to_status` | status | ✗ | `personnel.status` değerleri |
| `reason_code` | code32 | ✗ | |
| `note` | text | ✓ | |
| `effective_at` | ts | ✗ | |

## 5. Dönüşebilir Functional Area yönetişimi (ERD-02 §4.1, 03 §4.3)

### 5.1 `functional_areas` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `organization_id` | fk | ✗ | → `organizations` |
| `code` | code | ✗ | UNIQUE `(organization_id, code)`; ilk kayıt `social_media` |
| `governance_mode` | status | ✗ | [`functional_team`, `org_unit_owned`, `hybrid`] |
| `default_locale` | locale | ✗ | |
| `default_timezone` | tz | ✗ | |
| `classification_id` | fk | ✗ | → `security_classifications` |
| `status` | status | ✗ | [`planned`, `active`, `suspended`, `retired`] |
| `valid_from` | date | ✗ | |
| `valid_until` | date | ✓ | `CHECK` tarih sırası |

### 5.2 `functional_area_translations` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` (CASCADE) |
| `locale` | locale | ✗ | UNIQUE `(functional_area_id, locale)` |
| `name` | name | ✗ | |
| `description` | text | ✓ | |

### 5.3 `functional_area_role_definitions` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `role_code` | code32 | ✗ | UNIQUE `(functional_area_id, role_code)`; sosyal medya: `function_owner`, `content_creator`, `reviewer`, `approver`, `publisher`, `analyst` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `is_owner_role` | bool | ✗ | Alan başına tek aktif owner kuralına tabi rol |
| `status` | status | ✗ | [`active`, `inactive`] |

### 5.4 `functional_area_role_capabilities` — M, sınıf I, +S1

ERD'deki JSON `capability_set` yerine (N-02).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `role_definition_id` | fk | ✗ | → `functional_area_role_definitions` (CASCADE) |
| `capability_code` | code | ✗ | UNIQUE `(role_definition_id, capability_code)`; örn. `social.content.create`, `social.content.approve`, `social.publish`, `social.metrics.read` |

### 5.5 `functional_area_responsibilities` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `role_code` | code32 | ✗ | Composite FK `(functional_area_id, role_code)` → `functional_area_role_definitions` |
| `personnel_id` | fk | ✓ | → `personnel` |
| `team_id` | fk | ✓ | → `teams` |
| `position_id` | fk | ✓ | → `positions` |
| `is_primary` | bool | ✗ | |
| `note` | text | ✓ | |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |
| `active_owner_guard` | fk (generated) | ✓ | `CASE WHEN role_code = 'function_owner' AND is_primary = 1 AND valid_until IS NULL THEN functional_area_id END`; UNIQUE |

`CHECK` XOR: personnel/team/position hedeflerinden tam biri dolu.

### 5.6 `functional_area_org_unit_bindings` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `org_unit_id` | fk | ✗ | → `org_units` |
| `binding_type` | status | ✗ | [`owner`, `contributor`, `governance`] |
| `approved_by_personnel_id` | fk | ✗ | → `personnel` |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |
| `active_owner_binding_guard` | fk (generated) | ✓ | `CASE WHEN binding_type = 'owner' AND valid_until IS NULL THEN functional_area_id END`; UNIQUE |

### 5.7 `functional_area_transition_events` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `from_mode` | status | ✗ | `governance_mode` değerleri |
| `to_mode` | status | ✗ | |
| `target_org_unit_id` | fk | ✓ | → `org_units`; departmanlaşmada hedef birim |
| `effective_at` | ts | ✗ | |
| `approved_by_personnel_id` | fk | ✗ | → `personnel` |
| `reason` | text | ✗ | |
| `decision_document_revision_id` | fk | ✓ | → `document_revisions` |

### 5.8 `functional_area_transition_items` — M, sınıf I, +S1 +S2

Departmanlaşmada etkilenen açık görev/onay/bildirim/sorumlulukların tekrar çalıştırılabilir transfer defteri.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `transition_event_id` | fk | ✗ | → `functional_area_transition_events` |
| `object_type` | code32 | ✗ | Registry değeri (`task`, `approval_request_step`, `notification_recipient`, `functional_area_responsibility`, `social_account`) |
| `object_id` | fk | ✗ | UNIQUE `(transition_event_id, object_type, object_id)` |
| `previous_owner_snapshot` | json | ✗ | Değiştirilmez |
| `new_owner_snapshot` | json | ✓ | |
| `transfer_action` | status | ✗ | [`reassign`, `notify`, `close`, `keep`] |
| `outcome` | status | ✗ | [`pending`, `done`, `skipped`, `failed`] |
| `processed_at` | ts | ✓ | |
| `safe_error_code` | code32 | ✓ | |

## 6. Yetkinlik, eğitim ve İK operasyonu (ERD-02 §4.2, 03 §5.4)

### 6.1 `competencies` — R, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `category` | code32 | ✗ | [`technical`, `engineering`, `software`, `field`, `commercial`, `management`, `language`, `hse`] |
| `max_level` | tint | ✗ | Varsayılan 5 |
| `status` | status | ✗ | [`active`, `inactive`] |

### 6.2 `personnel_competencies` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `competency_id` | fk | ✗ | → `competencies` |
| `source` | status | ✗ | [`self_declared`, `verified`]; UNIQUE `(personnel_id, competency_id, source)` |
| `level` | tint | ✗ | `CHECK (level >= 1)`; `max_level` üst sınırı servis kontrolü |
| `verified_by_personnel_id` | fk | ✓ | → `personnel`; `source = verified` ise zorunlu |
| `verified_at` | ts | ✓ | |
| `evidence_document_revision_id` | fk | ✓ | → `document_revisions` |
| `valid_until` | date | ✓ | |

### 6.3 `certifications` — R, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `issuer` | name | ✓ | |
| `validity_months` | sint | ✓ | NULL = süresiz |
| `is_field_mandatory` | bool | ✗ | Saha görevlendirmesi ön koşulu |
| `status` | status | ✗ | [`active`, `inactive`] |

### 6.4 `personnel_certifications` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `certification_id` | fk | ✗ | → `certifications` |
| `certificate_no` | code | ✓ | |
| `issued_on` | date | ✗ | UNIQUE `(personnel_id, certification_id, issued_on)` |
| `valid_until` | date | ✓ | Expiry tarama indeksi |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `verified_by_personnel_id` | fk | ✓ | → `personnel` |
| `status` | status | ✗ | [`valid`, `expiring`, `expired`, `revoked`]; scheduler günceller |

### 6.5 `trainings` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `training_kind` | status | ✗ | [`internal`, `external`, `online`, `on_the_job`] |
| `provider` | name | ✓ | |
| `planned_on` | date | ✓ | |
| `duration_hours` | `DECIMAL(6,2)` | ✓ | |
| `status` | status | ✗ | [`planned`, `completed`, `cancelled`] |

### 6.6 `training_attendances` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `training_id` | fk | ✗ | → `trainings` |
| `personnel_id` | fk | ✗ | → `personnel`; UNIQUE `(training_id, personnel_id)` |
| `attended_on` | date | ✓ | |
| `outcome` | status | ✗ | [`registered`, `attended`, `passed`, `failed`, `absent`] |
| `score` | `DECIMAL(5,2)` | ✓ | |
| `certificate_document_revision_id` | fk | ✓ | → `document_revisions` |

### 6.7 `leave_types` — R, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `is_paid` | bool | ✗ | |
| `requires_document` | bool | ✗ | |
| `max_days_per_year` | `DECIMAL(5,2)` | ✓ | |
| `status` | status | ✗ | [`active`, `inactive`] |

### 6.8 `leave_requests` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `leave_type_id` | fk | ✗ | → `leave_types` |
| `starts_on` | date | ✗ | |
| `ends_on` | date | ✗ | `CHECK (ends_on >= starts_on)` |
| `day_count` | `DECIMAL(5,2)` | ✗ | İş takvimine göre servis hesabı |
| `reason` | text | ✓ | |
| `status` | status | ✗ | [`draft`, `submitted`, `approved`, `rejected`, `cancelled`, `withdrawn`] |
| `submitted_at` | ts | ✓ | |
| `approval_request_id` | fk | ✓ | → `approval_requests` (ortak onay; N-03) |
| `document_revision_id` | fk | ✓ | → `document_revisions`; rapor/belge |

`leave_approvals` tablosu ortak onay modeli lehine kaldırılması önerilir (D-14).

### 6.9 `attendance_entries` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `work_date` | date | ✗ | |
| `timezone` | tz | ✗ | |
| `check_in_at` | ts | ✓ | |
| `check_out_at` | ts | ✓ | `CHECK (check_out_at IS NULL OR check_in_at IS NULL OR check_out_at > check_in_at)` |
| `source` | status | ✗ | [`manual`, `import`, `device`, `timesheet`] |
| `project_id` | fk | ✓ | → `projects` |
| `status` | status | ✗ | [`recorded`, `corrected`, `approved`] |

### 6.10 `timesheets` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `period_start` | date | ✗ | UNIQUE `(personnel_id, period_start)` |
| `period_end` | date | ✗ | `CHECK (period_end >= period_start)` |
| `status` | status | ✗ | [`draft`, `submitted`, `approved`, `rejected`, `locked`] |
| `submitted_at` | ts | ✓ | |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

### 6.11 `timesheet_lines` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `timesheet_id` | fk | ✗ | → `timesheets` |
| `work_date` | date | ✗ | Dönem içinde; servis kontrolü |
| `project_id` | fk | ✓ | → `projects` |
| `wbs_node_id` | fk | ✓ | → `wbs_nodes` |
| `activity_code` | code32 | ✗ | |
| `hours` | `DECIMAL(5,2)` | ✗ | `CHECK (hours > 0 AND hours <= 24)` |
| `note` | text | ✓ | |

### 6.12 `expense_claims` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `claim_no` | code32 | ✗ | UNIQUE |
| `currency_code` | currency | ✗ | → `currencies` |
| `total_amount` | money | ✗ | Satır toplamı; servis doğrular |
| `project_id` | fk | ✓ | → `projects` |
| `status` | status | ✗ | [`draft`, `submitted`, `approved`, `rejected`, `paid`, `cancelled`] |
| `submitted_at` | ts | ✓ | |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

`expense_approvals` tablosu ortak onay modeli lehine kaldırılması önerilir (D-14).

### 6.13 `expense_lines` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `expense_claim_id` | fk | ✗ | → `expense_claims` |
| `expense_date` | date | ✗ | |
| `category_code` | code32 | ✗ | |
| `description` | short | ✗ | |
| `amount` | money | ✗ | `CHECK (amount >= 0)` |
| `vat_amount` | money | ✓ | |
| `receipt_document_revision_id` | fk | ✓ | → `document_revisions` |
| `cbs_node_id` | fk | ✓ | → `cbs_nodes` |
| `sort_order` | sint | ✗ | |

### 6.14 `workforce_requests` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `request_no` | code32 | ✗ | UNIQUE |
| `requesting_org_unit_id` | fk | ✗ | → `org_units` |
| `requested_by_employee_id` | fk | ✗ | → `personnel` |
| `position_id` | fk | ✓ | → `positions` |
| `project_id` | fk | ✓ | → `projects` |
| `headcount` | sint | ✗ | `CHECK (headcount > 0)` |
| `needed_from` | date | ✗ | |
| `justification` | text | ✗ | |
| `status` | status | ✗ | [`draft`, `submitted`, `approved`, `rejected`, `fulfilled`, `cancelled`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

### 6.15 `staffing_options` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `workforce_request_id` | fk | ✗ | → `workforce_requests` |
| `option_type` | status | ✗ | [`internal_transfer`, `internal_assignment`, `new_hire`, `contractor`] |
| `personnel_id` | fk | ✓ | → `personnel`; iç seçeneklerde zorunlu |
| `note` | text | ✓ | |
| `is_selected` | bool | ✗ | |
| `selected_guard` | fk (generated) | ✓ | `CASE WHEN is_selected = 1 THEN workforce_request_id END`; UNIQUE |

### 6.16 `resource_assignments` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `project_id` | fk | ✓ | → `projects` |
| `workforce_request_id` | fk | ✓ | → `workforce_requests` |
| `role_code` | code32 | ✗ | Proje rolü kataloğu |
| `allocation_pct` | `DECIMAL(5,2)` | ✗ | `CHECK (> 0 AND <= 100)` |
| `status` | status | ✗ | [`planned`, `active`, `ended`, `cancelled`] |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |

### 6.17 `job_requisitions` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `workforce_request_id` | fk | ✗ | → `workforce_requests`; UNIQUE (1:1) |
| `requisition_no` | code32 | ✗ | UNIQUE |
| `hiring_manager_employee_id` | fk | ✗ | → `personnel` |
| `recruiter_employee_id` | fk | ✓ | → `personnel` |
| `status` | status | ✗ | [`draft`, `open`, `on_hold`, `closed`, `cancelled`] |
| `opened_at` | ts | ✓ | |
| `closed_at` | ts | ✓ | |

### 6.18 `job_post_versions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `job_requisition_id` | fk | ✗ | → `job_requisitions` |
| `version_no` | int | ✗ | UNIQUE `(job_requisition_id, version_no)` |
| `locale` | locale | ✗ | |
| `title` | name | ✗ | |
| `body` | longtext | ✗ | Sanitize edilmiş içerik |
| `status` | status | ✗ | [`draft`, `published`, `withdrawn`, `superseded`] |
| `published_at` | ts | ✓ | |
| `published_channel` | code | ✓ | |
| `content_hash` | hash | ✗ | |

### 6.19 `candidates` — M, sınıf R, +S1 +S2 +S3

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `candidate_no` | code32 | ✗ | UNIQUE |
| `display_name` | name | ✗ | |
| `normalized_email` | email | ✓ | UNIQUE |
| `phone` | code32 | ✓ | |
| `source` | status | ✗ | [`referral`, `portal`, `agency`, `direct`, `internal`] |
| `cv_document_revision_id` | fk | ✓ | → `document_revisions` (restricted sınıf) |
| `consent_status` | status | ✗ | [`pending`, `granted`, `withdrawn`, `expired`] |
| `consent_at` | ts | ✓ | |
| `retention_until` | date | ✓ | KVKK saklama sonu |
| `status` | status | ✗ | [`new`, `screening`, `active`, `hired`, `rejected`, `withdrawn`, `archived`] |

### 6.20 `applications` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `candidate_id` | fk | ✗ | → `candidates` |
| `job_requisition_id` | fk | ✗ | → `job_requisitions`; UNIQUE `(candidate_id, job_requisition_id)` |
| `applied_at` | ts | ✗ | |
| `stage` | status | ✗ | [`applied`, `screened`, `interview`, `offer`, `hired`, `rejected`, `withdrawn`] |
| `stage_changed_at` | ts | ✗ | |

### 6.21 `interviews` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `application_id` | fk | ✗ | → `applications` |
| `interview_type` | status | ✗ | [`phone`, `technical`, `hr`, `final`] |
| `scheduled_at` | ts | ✗ | |
| `timezone` | tz | ✗ | |
| `interviewer_employee_id` | fk | ✗ | → `personnel` |
| `outcome` | status | ✗ | [`pending`, `pass`, `fail`, `no_show`, `cancelled`] |
| `notes_document_revision_id` | fk | ✓ | → `document_revisions` |

### 6.22 `performance_cycles` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `period_start` | date | ✗ | |
| `period_end` | date | ✗ | `CHECK (period_end > period_start)` |
| `status` | status | ✗ | [`planned`, `open`, `closed`, `archived`] |

### 6.23 `performance_records` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `performance_cycle_id` | fk | ✗ | → `performance_cycles` |
| `personnel_id` | fk | ✗ | → `personnel`; UNIQUE `(performance_cycle_id, personnel_id)` |
| `reviewer_employee_id` | fk | ✗ | → `personnel` |
| `overall_rating` | `DECIMAL(4,2)` | ✓ | |
| `status` | status | ✗ | [`draft`, `self_review`, `manager_review`, `calibrated`, `final`, `acknowledged`] |
| `finalized_at` | ts | ✓ | |
| `acknowledged_at` | ts | ✓ | |

### 6.24 `development_actions` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `performance_record_id` | fk | ✗ | → `performance_records` |
| `action_type` | code32 | ✗ | [`training`, `mentoring`, `assignment`, `certification`, `other`] |
| `description` | text | ✗ | |
| `due_at` | ts | ✗ | |
| `status` | status | ✗ | [`open`, `in_progress`, `done`, `cancelled`] |
| `completed_at` | ts | ✓ | |

## 7. Bu bölümün DB-G8 kontrol listesi

- Bütün tablolar ERD-01/ERD-02 ile eşlendi; sözlüğe eklenen tablolar N-02, N-07 ve N-08 notlarında listelendi.
- Açık kararlar: D-07 (collation), D-12 (bildirim tablo adı), D-13 (capability çocuk tablosu), D-14 (İK onayları ortak modelde), D-15 (personnel kimlik tipi), D-16 (RBAC şeması) ve gerçek organizasyon verisi (03 §19 madde 1).
- Bu sözlük migration üretimine izin vermez; DB-G8 onayından sonra migration dosyaları [16](16-migration-uretim-sirasi-ve-dba-teslim-paketi.md) sırasına göre hazırlanır ve kodlama ajanları tarafından hiçbir zaman çalıştırılmaz.
